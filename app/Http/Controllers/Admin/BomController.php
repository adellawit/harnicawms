<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\FifoCostService;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BomController extends Controller
{
    /**
     * Daftar produk jadi (FINISHED_GOOD) beserta status resep (BOM)-nya.
     * Resep diinput per produk lewat aksi di tiap baris.
     */
    public function index()
    {
        $variants = ProductVariant::query()
            ->with(['product.nature', 'product.defaultUnit'])
            ->whereHas('product', function ($q) {
                $q->whereNull('deleted_at')
                    ->whereHas('nature', fn ($n) => $n->where('code', 'FINISHED_GOOD'));
            })
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get();

        // Map BOM aktif per varian (untuk status & link)
        $boms = BillOfMaterial::with('items')
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->get()
            ->keyBy('product_variant_id');

        return view('admin.bom.index', compact('variants', 'boms'));
    }

    public function create(Request $request)
    {
        $companyId = optional(WmsContext::distributor())->id;
        [$wipId, $wipBranchId] = $this->resolveWipContext($companyId);

        $components = $this->buildComponentOptions($wipBranchId, $wipId);

        // Produk jadi yang dipilih dari daftar (terkunci); fallback ke dropdown bila kosong
        $selected = null;
        if ($request->filled('product_variant_id')) {
            $selected = ProductVariant::with(['product.defaultUnit'])->find($request->query('product_variant_id'));
        }

        $outputs = WmsContext::variantOptions('FINISHED_GOOD'); // fallback dropdown produk jadi

        return view('admin.bom.create', compact('outputs', 'components', 'selected'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'product_variant_id' => ['required', 'string'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.variant_id' => ['required', 'string'],
            'components.*.quantity' => ['required', 'numeric', 'min:0.000001'],
            'components.*.unit_id' => ['required', 'string'],
        ]);

        $outputVariant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);
        $userId = Auth::id();
        $companyId = optional(WmsContext::distributor())->id;

        // Cegah resep ganda untuk produk yang sama
        if (BillOfMaterial::where('product_variant_id', $outputVariant->id)->exists()) {
            return redirect()->route('bom.index')
                ->with('error', 'Produk ini sudah memiliki resep (BOM). Silakan edit resep yang sudah ada.');
        }

        [$wipId, $wipBranchId] = $this->resolveWipContext($companyId);

        DB::transaction(function () use ($data, $outputVariant, $userId, $companyId, $wipId, $wipBranchId) {
            $bom = BillOfMaterial::create([
                'company_id' => $companyId,
                'product_id' => $outputVariant->product_id,
                'product_variant_id' => $outputVariant->id,
                'output_unit_id' => $outputVariant->product->default_unit_id,
                'output_quantity' => 1,
                'name' => $data['name'],
                'version' => 1,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->syncItems($bom, $data['components'], $userId, $wipBranchId, $wipId);
        });

        return redirect()->route('bom.index')->with('success', 'Resep (BOM) berhasil dibuat.');
    }

    public function edit(string $id)
    {
        $bom = BillOfMaterial::with(['product', 'variant.product.defaultUnit'])->findOrFail($id);

        [$wipId, $wipBranchId] = $this->resolveWipContext($bom->company_id);
        $components = $this->buildComponentOptions($wipBranchId, $wipId);

        $items = $bom->items()->with('componentVariant')->get()->map(fn (BomItem $item) => [
            'variant_id' => $item->component_variant_id,
            'quantity' => (float) $item->quantity,
            'unit_id' => $item->unit_id,
            'old_cost' => (float) ($item->last_unit_cost ?? 0),
            'new_cost' => FifoCostService::currentUnitCost($item->component_variant_id, $wipBranchId, $item->unit_id, $wipId),
        ])->values()->all();

        return view('admin.bom.edit', compact('bom', 'components', 'items'));
    }

    public function update(Request $request, string $id)
    {
        $bom = BillOfMaterial::with('product')->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.variant_id' => ['required', 'string'],
            'components.*.quantity' => ['required', 'numeric', 'min:0.000001'],
            'components.*.unit_id' => ['required', 'string'],
        ]);

        $userId = Auth::id();
        [$wipId, $wipBranchId] = $this->resolveWipContext($bom->company_id);

        DB::transaction(function () use ($bom, $data, $userId, $wipId, $wipBranchId) {
            $bom->update([
                'name' => $data['name'],
                'updated_by' => $userId,
            ]);

            $bom->items()->delete();
            $this->syncItems($bom, $data['components'], $userId, $wipBranchId, $wipId);
        });

        return redirect()->route('bom.index')->with('success', 'Resep (BOM) berhasil diperbarui.');
    }

    /**
     * Bahan baku (RAW_MATERIAL/SEMI_FINISHED) + satuan yang berlaku per bahan + HPP terkini per satuan.
     */
    private function buildComponentOptions(string $wipBranchId, ?string $wipId): array
    {
        return ProductVariant::query()
            ->with([
                'product.nature',
                'product.defaultUnit',
                'product.unitConversions.fromUnit',
                'product.unitConversions.toUnit',
            ])
            ->whereHas('product', function ($q) {
                $q->where('is_stock_item', true)
                    ->whereNull('deleted_at')
                    ->whereHas('nature', fn ($n) => $n->whereIn('code', ['RAW_MATERIAL', 'SEMI_FINISHED']));
            })
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get()
            ->map(function (ProductVariant $v) use ($wipBranchId, $wipId) {
                $product = $v->product;

                $units = collect();
                if ($product?->defaultUnit) {
                    $units->push($product->defaultUnit);
                }
                foreach ($product?->unitConversions ?? [] as $conv) {
                    $units->push($conv->fromUnit);
                    $units->push($conv->toUnit);
                }
                $units = $units->filter()->unique('id');

                $costs = $units->mapWithKeys(fn ($u) => [
                    $u->id => FifoCostService::currentUnitCost($v->id, $wipBranchId, $u->id, $wipId),
                ]);

                return [
                    'id' => $v->id,
                    'label' => $v->display_name,
                    'nature' => $product?->nature?->code,
                    'default_unit_id' => $product?->default_unit_id,
                    'units' => $units->map(fn ($u) => [
                        'id' => $u->id,
                        'label' => $u->symbol ? $u->name.' ('.$u->symbol.')' : $u->name,
                    ])->values()->all(),
                    'costs' => $costs,
                ];
            })
            ->filter(fn ($c) => ! empty($c['units']))
            ->values()
            ->all();
    }

    /**
     * Ganti daftar bahan BOM; HPP saat ini di-snapshot sebagai baseline "HPP lama" untuk perbandingan berikutnya.
     */
    private function syncItems(BillOfMaterial $bom, array $rows, ?string $userId, string $wipBranchId, ?string $wipId): void
    {
        foreach ($rows as $row) {
            $variant = ProductVariant::with('product')->find($row['variant_id']);
            if (! $variant) {
                continue;
            }

            $unitId = $row['unit_id'] ?? $variant->product->default_unit_id;

            BomItem::create([
                'bom_id' => $bom->id,
                'component_product_id' => $variant->product_id,
                'component_variant_id' => $variant->id,
                'unit_id' => $unitId,
                'quantity' => (float) $row['quantity'],
                'last_unit_cost' => FifoCostService::currentUnitCost($variant->id, $wipBranchId, $unitId, $wipId),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    /**
     * Gudang acuan untuk lookup HPP bahan baku. WIP diprioritaskan (sesuai desain awal),
     * tapi HPP bahan baku sebenarnya tercatat di gudang RAW_MATERIAL, jadi fallback berjenjang
     * dipakai supaya HPP tetap tampil walau gudang WIP belum ada (mis. di environment lokal/baru).
     * company_id != branch_id di skema ini, jadi branch_id HARUS diambil dari gudang yang nyata ada,
     * tidak boleh asal pakai company_id sebagai pengganti.
     */
    private function resolveWipContext(?string $companyId): array
    {
        $warehouse = Warehouse::inventoryActive()
            ->where('company_id', $companyId)
            ->orderByRaw("CASE warehouse_type_code WHEN 'WIP' THEN 0 WHEN 'RAW_MATERIAL' THEN 1 WHEN 'FG' THEN 2 ELSE 3 END")
            ->orderByDesc('is_default')
            ->first();

        $wipId = optional($warehouse)->id;
        $wipBranchId = optional($warehouse)->branch_id ?? $wipId ?? $companyId;

        return [$wipId, $wipBranchId];
    }

    public function show(string $id)
    {
        $bom = BillOfMaterial::with(['product', 'variant', 'outputUnit', 'items.componentVariant.product.unitConversions', 'items.unit'])
            ->findOrFail($id);

        [$wipId, $wipBranchId] = $this->resolveWipContext($bom->company_id);

        // Biaya per komponen: HPP layer FEFO dikonversi ke satuan BOM item
        $itemCosts = $bom->items->mapWithKeys(function ($item) use ($wipId, $wipBranchId) {
            $unitCost = FifoCostService::currentUnitCost(
                $item->component_variant_id,
                $wipBranchId,
                $item->unit_id,
                $wipId
            );

            return [
                $item->id => [
                    'unit_cost' => $unitCost,
                    'line_total' => $unitCost * (float) $item->quantity,
                ],
            ];
        });

        $totalCost = $itemCosts->sum('line_total');

        return view('admin.bom.show', compact('bom', 'itemCosts', 'totalCost'));
    }

    public function destroy(string $id)
    {
        $bom = BillOfMaterial::findOrFail($id);
        $bom->delete();

        return redirect()->route('bom.index')->with('success', 'BOM dihapus.');
    }
}
