<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\ProductionOrder;
use App\Models\Warehouse;
use App\Services\StockAvailabilityService;
use App\Services\Manufacturing\ProductionService;
use App\Services\Manufacturing\ProductionSimulationService;
use App\Support\InventoryWarehouseContext;
use App\Support\ProductionQuantityNormalizer;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProductionOrderController extends Controller
{
    public function index()
    {
        $orders = ProductionOrder::with([
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'product.defaultUnit',
            'variant',
            'outputUnit',
            'branch',
            'sourceWarehouse',
            'outputWarehouse',
        ])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.production.index', compact('orders'));
    }

    public function create()
    {
        $boms = BillOfMaterial::with([
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'product.defaultUnit',
            'outputUnit',
            'variant',
            'items.componentVariant.product',
            'items.unit',
        ])
            ->where('is_active', true)
            ->whereHas('product')
            ->orderByDesc('created_at')
            ->get();

        $bomCatalog = $boms->map(function (BillOfMaterial $bom) {
            return [
                'id' => $bom->id,
                'output_unit_id' => $bom->output_unit_id,
                'output_quantity' => (float) $bom->output_quantity,
                'output_unit' => $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? '',
                'units' => ProductionSimulationService::unitOptions($bom->product),
            ];
        })->values();

        $distributor = WmsContext::distributor();
        $wip = WmsContext::wipWarehouse(optional($distributor)->id);
        $fg = WmsContext::finishedGoodsWarehouse(optional($distributor)->id);
        $warehouses = WmsContext::accessibleWarehouses();

        return view('admin.production.create', compact('boms', 'bomCatalog', 'distributor', 'wip', 'fg', 'warehouses'));
    }

    public function bomPreview(Request $request)
    {
        $data = $request->validate([
            'bom_id' => ['required', 'string'],
            'source_warehouse_id' => ['required', 'uuid'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
        ]);

        $bom = BillOfMaterial::with(['items.componentVariant.product', 'items.unit', 'product.unitConversions', 'product.defaultUnit', 'outputUnit'])
            ->findOrFail($data['bom_id']);

        $warehouse = InventoryWarehouseContext::assertAccessible($data['source_warehouse_id']);
        $branchId = $warehouse->branch_id ?: $warehouse->company_id ?: auth('web')->user()?->getBranchIdForTransaction();
        $plannedUnitId = $data['planned_unit_id'] ?? $bom->output_unit_id;

        try {
            $scale = ProductionQuantityNormalizer::materialScale(
                $bom,
                (float) $data['planned_qty'],
                $plannedUnitId
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $items = $bom->items->map(function ($item) use ($warehouse, $branchId, $scale) {
            $available = StockAvailabilityService::availableQuantity(
                $item->component_variant_id,
                $branchId,
                $item->unit_id,
                $warehouse->id
            );

            return [
                'label' => $item->componentVariant?->display_name ?? $item->componentProduct?->name,
                'qty' => (float) $item->quantity * $scale,
                'unit' => $item->unit?->symbol ?? $item->unit?->name ?? '',
                'available' => $available,
            ];
        })->values();

        return response()->json([
            'warehouse_name' => $warehouse->name,
            'items' => $items,
        ]);
    }

    public function simulate(Request $request)
    {
        $data = $request->validate([
            'bom_id' => ['required', 'string'],
            'source_warehouse_id' => ['required', 'uuid'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'production_unit_id' => ['nullable', 'uuid'],
        ]);

        $bom = BillOfMaterial::with([
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'product.defaultUnit',
            'outputUnit',
            'variant',
            'items.componentVariant.product.unitConversions.fromUnit',
            'items.componentVariant.product.unitConversions.toUnit',
            'items.componentVariant.product.defaultUnit',
            'items.unit',
        ])->findOrFail($data['bom_id']);

        $warehouse = InventoryWarehouseContext::assertAccessible($data['source_warehouse_id']);
        $branchId = $warehouse->branch_id ?: $warehouse->company_id ?: auth('web')->user()?->getBranchIdForTransaction();
        $productionUnitId = $data['production_unit_id'] ?? $bom->output_unit_id;

        try {
            $result = ProductionSimulationService::simulate(
                $bom,
                $branchId,
                $warehouse->id,
                (float) $data['planned_qty'],
                $productionUnitId
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal menghitung simulasi: '.$e->getMessage()], 500);
        }

        $result['warehouse_name'] = $warehouse->name;

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $accessibleWarehouseIds = WmsContext::accessibleWarehouseIds();

        $data = $request->validate([
            'bom_id' => ['required', 'string'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
            'source_warehouse_id' => ['required', 'uuid', Rule::in($accessibleWarehouseIds)],
            'output_warehouse_id' => ['required', 'uuid', Rule::in($accessibleWarehouseIds)],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'production_date' => ['nullable', 'date'],
            'output_expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'complete' => ['nullable'],
        ]);

        $bom = BillOfMaterial::with(['product.unitConversions', 'product.defaultUnit', 'outputUnit'])->findOrFail($data['bom_id']);
        $plannedUnitId = $data['planned_unit_id'] ?? $bom->output_unit_id;

        try {
            $plannedInOutputUnit = ProductionQuantityNormalizer::toBomOutputUnit(
                $bom,
                (float) $data['planned_qty'],
                $plannedUnitId
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['planned_qty' => $e->getMessage()]);
        }

        $distributor = WmsContext::distributor();
        $distId = optional($distributor)->id;
        $sourceWarehouse = Warehouse::findOrFail($data['source_warehouse_id']);
        $outputWarehouse = Warehouse::findOrFail($data['output_warehouse_id']);
        $userId = Auth::id();
        $branchId = $outputWarehouse->branch_id
            ?: $sourceWarehouse->branch_id
            ?: auth('web')->user()?->getBranchIdForTransaction();

        $order = ProductionOrder::create([
            'order_number' => ProductionService::generateNumber(),
            'production_date' => $data['production_date'] ?? now()->toDateString(),
            'output_expiry_date' => $data['output_expiry_date'] ?? null,
            'company_id' => $distId,
            'branch_id' => $branchId,
            'source_warehouse_id' => $sourceWarehouse->id,
            'output_warehouse_id' => $outputWarehouse->id,
            'bom_id' => $bom->id,
            'product_id' => $bom->product_id,
            'product_variant_id' => $bom->product_variant_id,
            'output_unit_id' => $bom->output_unit_id,
            'planned_qty' => $plannedInOutputUnit,
            'overhead_cost' => (float) ($data['overhead_cost'] ?? 0),
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if ($request->boolean('complete')) {
            try {
                ProductionService::complete($order, $userId);
            } catch (\Throwable $e) {
                return redirect()->route('production.show', $order->id)
                    ->with('error', 'Order dibuat tapi gagal diselesaikan: ' . $e->getMessage());
            }

            return redirect()->route('production.show', $order->id)
                ->with('success', 'Produksi selesai. HPP produk jadi telah dihitung dari bahan baku (FIFO).');
        }

        return redirect()->route('production.show', $order->id)->with('success', 'Production Order dibuat (draft).');
    }

    public function show(string $id)
    {
        $order = ProductionOrder::with([
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'product.defaultUnit',
            'variant',
            'outputUnit',
            'branch',
            'sourceWarehouse',
            'outputWarehouse',
            'bom.items.componentVariant.product.unitConversions',
            'bom.items.componentVariant.product.defaultUnit',
            'materials.componentVariant.product.unitConversions',
            'materials.componentVariant.product.defaultUnit',
            'materials.componentProduct.unitConversions',
            'materials.componentProduct.defaultUnit',
            'materials.unit',
            'outputs.variant.product.unitConversions',
            'outputs.variant.product.defaultUnit',
            'outputs.unit',
        ])->findOrFail($id);

        return view('admin.production.show', compact('order'));
    }

    public function complete(string $id)
    {
        $order = ProductionOrder::findOrFail($id);
        try {
            ProductionService::complete($order, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menyelesaikan produksi: ' . $e->getMessage());
        }

        return redirect()->route('production.show', $order->id)
            ->with('success', 'Produksi selesai. HPP produk jadi telah dihitung (FIFO).');
    }
}
