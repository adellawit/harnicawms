<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\ProductionOrder;
use App\Models\Warehouse;
use App\Services\StockAvailabilityService;
use App\Services\Manufacturing\ProductionService;
use App\Services\Manufacturing\ProductionSimulationService;
use App\Support\ManufacturingWarehouseResolver;
use App\Support\ProductionQuantityNormalizer;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        return view('admin.production.create');
    }

    public function bomForProduct(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
        ]);

        $bom = BillOfMaterial::with([
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'product.defaultUnit',
            'outputUnit',
            'variant',
        ])
            ->where('product_variant_id', $data['product_variant_id'])
            ->where('is_active', true)
            ->first();

        if (! $bom) {
            return response()->json(['message' => 'Produk ini belum punya resep (BOM).'], 404);
        }

        return response()->json([
            'bom_id' => $bom->id,
            'output_unit_id' => $bom->output_unit_id,
            'output_quantity' => (float) $bom->output_quantity,
            'output_unit' => $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? '',
            'units' => ProductionSimulationService::unitOptions($bom->product),
        ]);
    }

    public function bomPreview(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
        ]);

        $bom = BillOfMaterial::with(['items.componentVariant.product', 'items.unit', 'product.unitConversions', 'product.defaultUnit', 'outputUnit'])
            ->where('product_variant_id', $data['product_variant_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $distId = optional(WmsContext::distributor())->id;
        [$sourceWarehouseId, $branchId] = ManufacturingWarehouseResolver::resolveMaterialWarehouse($distId);
        $warehouse = $sourceWarehouseId ? Warehouse::find($sourceWarehouseId) : null;
        $plannedUnitId = $data['planned_unit_id'] ?? $bom->output_unit_id;

        if (! $warehouse) {
            return response()->json(['message' => 'Tidak ada gudang aktif untuk company ini.'], 422);
        }

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
            'product_variant_id' => ['required', 'string'],
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
        ])
            ->where('product_variant_id', $data['product_variant_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $distId = optional(WmsContext::distributor())->id;
        [$sourceWarehouseId, $branchId] = ManufacturingWarehouseResolver::resolveMaterialWarehouse($distId);
        $warehouse = $sourceWarehouseId ? Warehouse::find($sourceWarehouseId) : null;
        $productionUnitId = $data['production_unit_id'] ?? $bom->output_unit_id;

        if (! $warehouse) {
            return response()->json(['message' => 'Tidak ada gudang aktif untuk company ini.'], 422);
        }

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
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'production_date' => ['nullable', 'date'],
            'output_expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'mark_pending_receiving' => ['nullable'],
        ]);

        $bom = BillOfMaterial::with(['product.unitConversions', 'product.defaultUnit', 'outputUnit'])
            ->where('product_variant_id', $data['product_variant_id'])
            ->where('is_active', true)
            ->first();

        if (! $bom) {
            return back()->withInput()->withErrors(['product_variant_id' => 'Produk ini belum punya resep (BOM). Buat resep dulu di menu Bill of Materials.']);
        }

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
        $userId = Auth::id();

        [$sourceWarehouseId, $materialBranchId] = ManufacturingWarehouseResolver::resolveMaterialWarehouse($distId);
        [$outputWarehouseId, $outputBranchId] = ManufacturingWarehouseResolver::resolveOutputWarehouse($distId);
        $branchId = $outputBranchId ?: $materialBranchId ?: auth('web')->user()?->getBranchIdForTransaction();

        $order = ProductionOrder::create([
            'order_number' => ProductionService::generateNumber(),
            'production_date' => $data['production_date'] ?? now()->toDateString(),
            'output_expiry_date' => $data['output_expiry_date'] ?? null,
            'company_id' => $distId,
            'branch_id' => $branchId,
            'source_warehouse_id' => $sourceWarehouseId,
            'output_warehouse_id' => $outputWarehouseId,
            'bom_id' => $bom->id,
            'product_id' => $bom->product_id,
            'product_variant_id' => $bom->product_variant_id,
            'output_unit_id' => $bom->output_unit_id,
            'planned_qty' => $plannedInOutputUnit,
            'overhead_cost' => (float) ($data['overhead_cost'] ?? 0),
            'status' => $request->boolean('mark_pending_receiving') ? 'pending_receiving' : 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return redirect()->route('production.show', $order->id)->with('success', 'Production Order dibuat.');
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
