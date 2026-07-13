<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\ProductionOrder;
use App\Models\ProductUnit;
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

            $componentProduct = $item->componentVariant?->product ?? $item->componentProduct;
            $isSmallestUnit = $componentProduct && $item->unit_id === $componentProduct->getSmallestUnitId();

            return [
                'label' => $item->componentVariant?->display_name ?? $item->componentProduct?->name,
                'qty' => (float) $item->quantity * $scale,
                'unit' => $item->unit?->symbol ?? $item->unit?->name ?? '',
                // Stok yang sudah melewati beberapa kali konversi non-power-of-10 (mis. ÷30)
                // bisa membawa sisa desimal sangat kecil (mis. 5079.99991 / 4956.0014). Ini
                // murni kosmetik untuk preview — kalau satuannya adalah satuan terkecil produk
                // (mis. sachet), pecahan selalu noise (fisiknya tidak mungkin pecahan sachet)
                // jadi dibulatkan tanpa syarat; kalau bukan, snap hanya kalau dekat bulat.
                'available' => ProductionQuantityNormalizer::snapDisplayQty($available, $isSmallestUnit),
            ];
        })->values();

        return response()->json([
            'warehouse_name' => $warehouse->name,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'production_date' => ['nullable', 'date'],
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

    public function edit(string $id)
    {
        $order = ProductionOrder::with(['variant.product.defaultUnit', 'bom'])->findOrFail($id);

        if ($order->status !== 'draft') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Hanya production order berstatus Draft yang bisa diedit.');
        }

        $units = $order->bom ? ProductionSimulationService::unitOptions($order->bom->product) : [];

        return view('admin.production.edit', compact('order', 'units'));
    }

    public function update(Request $request, string $id)
    {
        $order = ProductionOrder::with('bom')->findOrFail($id);

        if ($order->status !== 'draft') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Hanya production order berstatus Draft yang bisa diedit.');
        }

        $data = $request->validate([
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'production_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $bom = $order->bom;

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

        $order->update([
            'planned_qty' => $plannedInOutputUnit,
            'overhead_cost' => (float) ($data['overhead_cost'] ?? 0),
            'production_date' => $data['production_date'] ?? $order->production_date,
            'notes' => $data['notes'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('production.show', $order->id)->with('success', 'Production Order diperbarui.');
    }

    public function destroy(string $id)
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->status !== 'draft') {
            return back()->with('error', 'Hanya production order berstatus Draft yang bisa dihapus.');
        }

        $order->delete();

        return redirect()->route('production.index')->with('success', 'Production Order dihapus.');
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

    public function start(string $id)
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->status !== 'draft') {
            return back()->with('error', 'Hanya production order berstatus Draft yang bisa dimulai.');
        }

        $order->update(['status' => 'in_progress', 'updated_by' => Auth::id()]);

        return redirect()->route('production.show', $order->id)->with('success', 'Produksi dimulai.');
    }

    public function finish(string $id)
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->status !== 'in_progress') {
            return back()->with('error', 'Hanya production order yang sedang dikerjakan yang bisa ditandai selesai.');
        }

        $order->update(['status' => 'pending_receiving', 'updated_by' => Auth::id()]);

        return redirect()->route('production.show', $order->id)->with('success', 'Produksi selesai di lantai. Lanjutkan ke Receiving untuk mencatat hasil aktual.');
    }

    public function receiveView(string $id)
    {
        $order = ProductionOrder::with([
            'product.defaultUnit',
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'variant',
            'outputUnit',
            'bom.items.componentVariant.product',
            'bom.items.unit',
        ])->findOrFail($id);

        if ($order->status !== 'pending_receiving') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Production order ini belum siap untuk diterima.');
        }

        $units = ProductionSimulationService::unitOptions($order->product);

        // Faktor konversi tiap satuan RELATIF ke output_unit_id order ini, dipakai
        // JS di halaman Receiving untuk preview live (server tetap hitung ulang sendiri
        // saat submit — ini murni tampilan).
        $unitFactors = collect($units)->mapWithKeys(function (array $unit) use ($order) {
            $factor = $order->product->convertQuantity(1.0, $unit['id'], $order->output_unit_id) ?? 1.0;

            return [$unit['id'] => $factor];
        });

        return view('admin.production.receive', compact('order', 'units', 'unitFactors'));
    }

    public function receive(Request $request, string $id)
    {
        $order = ProductionOrder::with('bom')->findOrFail($id);

        $data = $request->validate([
            'actual_qty' => ['required', 'numeric', 'min:0.000001'],
            'actual_unit_id' => ['required', 'uuid'],
            'output_expiry_date' => ['nullable', 'date'],
        ]);

        if (! $order->bom) {
            return back()->withInput()->withErrors(['product_variant_id' => 'Produk ini belum punya resep (BOM). Buat resep dulu di menu Bill of Materials.']);
        }

        try {
            $actualQtyInOutputUnit = ProductionQuantityNormalizer::toBomOutputUnit(
                $order->bom,
                (float) $data['actual_qty'],
                $data['actual_unit_id']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['actual_qty' => $e->getMessage()]);
        }

        $order->output_expiry_date = $data['output_expiry_date'] ?? null;

        try {
            ProductionService::receive($order, $actualQtyInOutputUnit, Auth::id());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Gagal menerima hasil produksi: ' . $e->getMessage());
        }

        return redirect()->route('production.receive.print', [
            'id' => $order->id,
            'unit_id' => $data['actual_unit_id'],
            'quantity' => (int) round((float) $data['actual_qty']),
        ])->with('success', 'Hasil produksi diterima. Stok bahan baku terpotong dan produk jadi masuk gudang.');
    }

    public function receivePrint(Request $request, string $id)
    {
        $order = ProductionOrder::with(['product', 'variant'])->findOrFail($id);

        if ($order->status !== 'completed') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Production order ini belum selesai diterima.');
        }

        $data = $request->validate([
            'unit_id' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $unit = ProductUnit::find($data['unit_id']);
        if (! $unit) {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Satuan cetak barcode tidak valid.');
        }

        $smallestUnitId = $order->product?->getSmallestUnitId();
        $smallestUnit = $smallestUnitId ? ProductUnit::find($smallestUnitId) : null;
        // Toggle "sertakan satuan terkecil" cuma relevan kalau satuan yang diterima
        // BUKAN satuan terkecil itu sendiri (kalau sama, tidak ada yang bisa dikecualikan).
        $showSmallestUnitToggle = $smallestUnit && $smallestUnit->id !== $unit->id;

        $distributor = WmsContext::distributor();
        $distributorName = strtoupper($distributor?->legal_name ?: $distributor?->name ?: config('app.name'));

        return view('admin.production.receive-print', [
            'order' => $order,
            'unit' => $unit,
            'quantity' => (int) $data['quantity'],
            'distributorName' => $distributorName,
            'smallestUnit' => $smallestUnit,
            'showSmallestUnitToggle' => $showSmallestUnitToggle,
        ]);
    }
}
