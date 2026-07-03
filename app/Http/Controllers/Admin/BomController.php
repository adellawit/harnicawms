<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\ProductVariant;
use App\Services\FifoCostService;
use App\Services\Manufacturing\BomRecipeCalculatorService;
use App\Services\Manufacturing\ProductionSimulationService;
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
        // Bahan baku + daftar satuan yang berlaku untuk MASING-MASING bahan
        // (satuan default produk + satuan dari konversi yang di-set di bahan tsb)
        $components = ProductVariant::query()
            ->with([
                'product.nature',
                'product.defaultUnit',
                'product.unitConversions.fromUnit',
                'product.unitConversions.toUnit',
            ])
            ->whereHas('product', fn ($q) => $q->where('is_stock_item', true)->whereNull('deleted_at'))
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get()
            ->map(function (ProductVariant $v) {
                $product = $v->product;

                $units = collect();
                if ($product?->defaultUnit) {
                    $units->push($product->defaultUnit);
                }
                foreach ($product?->unitConversions ?? [] as $conv) {
                    $units->push($conv->fromUnit);
                    $units->push($conv->toUnit);
                }
                $units = $units->filter()->unique('id')->map(fn ($u) => [
                    'id' => $u->id,
                    'label' => $u->symbol ? $u->name.' ('.$u->symbol.')' : $u->name,
                ])->values()->all();

                return [
                    'id' => $v->id,
                    'label' => $v->display_name,
                    'nature' => $product?->nature?->code,
                    'default_unit_id' => $product?->default_unit_id,
                    'units' => $units,
                ];
            })
            ->filter(fn ($c) => ! empty($c['units']))
            ->values()
            ->all();

        // Produk jadi yang dipilih dari daftar (terkunci); fallback ke dropdown bila kosong
        $selected = null;
        $outputUnits = [];
        if ($request->filled('product_variant_id')) {
            $selected = ProductVariant::with(['product.unitConversions', 'product.defaultUnit'])->find($request->query('product_variant_id'));
            if ($selected?->product) {
                $outputUnits = ProductionSimulationService::unitOptions($selected->product);
            }
        }

        $outputs = WmsContext::variantOptions('FINISHED_GOOD'); // fallback dropdown produk jadi

        return view('admin.bom.create', compact('outputs', 'components', 'selected', 'outputUnits'));
    }

    public function outputUnits(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
        ]);

        $variant = ProductVariant::with(['product.unitConversions', 'product.defaultUnit'])
            ->findOrFail($data['product_variant_id']);

        if (! $variant->product) {
            return response()->json(['units' => []]);
        }

        return response()->json([
            'units' => ProductionSimulationService::unitOptions($variant->product),
            'default_unit_id' => $variant->product->default_unit_id,
        ]);
    }

    public function calculate(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
            'output_qty' => ['required', 'numeric', 'min:0.000001'],
            'output_unit_id' => ['required', 'uuid'],
            'component_variant_id' => ['required', 'string'],
            'component_unit_id' => ['required', 'uuid'],
        ]);

        $outputVariant = ProductVariant::with(['product.unitConversions', 'product.defaultUnit'])
            ->findOrFail($data['product_variant_id']);
        $componentVariant = ProductVariant::with(['product.unitConversions', 'product.defaultUnit'])
            ->findOrFail($data['component_variant_id']);

        $result = BomRecipeCalculatorService::suggest(
            $outputVariant,
            (float) $data['output_qty'],
            $data['output_unit_id'],
            $componentVariant,
            $data['component_unit_id']
        );

        if (! $result) {
            return response()->json(['message' => 'Tidak dapat menghitung kebutuhan bahan. Periksa konversi satuan produk.'], 422);
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'product_variant_id' => ['required', 'string'],
            'output_quantity' => ['required', 'numeric', 'min:0.000001'],
            'output_unit_id' => ['required', 'uuid'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.variant_id' => ['required', 'string'],
            'components.*.quantity' => ['required', 'numeric', 'min:0.000001'],
            'components.*.unit_id' => ['required', 'string'],
        ]);

        $outputVariant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);
        $userId = Auth::id();

        // Cegah resep ganda untuk produk yang sama
        if (BillOfMaterial::where('product_variant_id', $outputVariant->id)->exists()) {
            return redirect()->route('bom.index')
                ->with('error', 'Produk ini sudah memiliki resep (BOM). Hapus dulu yang lama untuk membuat ulang.');
        }

        DB::transaction(function () use ($data, $outputVariant, $userId) {
            $bom = BillOfMaterial::create([
                'company_id' => optional(WmsContext::distributor())->id,
                'product_id' => $outputVariant->product_id,
                'product_variant_id' => $outputVariant->id,
                'output_unit_id' => $data['output_unit_id'],
                'output_quantity' => (float) $data['output_quantity'],
                'name' => $data['name'],
                'version' => 1,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($data['components'] as $row) {
                $variant = ProductVariant::with('product')->find($row['variant_id']);
                if (! $variant) {
                    continue;
                }
                BomItem::create([
                    'bom_id' => $bom->id,
                    'component_product_id' => $variant->product_id,
                    'component_variant_id' => $variant->id,
                    'unit_id' => $row['unit_id'] ?? $variant->product->default_unit_id,
                    'quantity' => (float) $row['quantity'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        });

        return redirect()->route('bom.index')->with('success', 'Resep (BOM) berhasil dibuat.');
    }

    public function show(string $id)
    {
        $bom = BillOfMaterial::with(['product', 'variant', 'outputUnit', 'items.componentVariant.product.unitConversions', 'items.unit'])
            ->findOrFail($id);

        // Ambil WIP warehouse untuk lookup harga bahan baku
        $wip = WmsContext::wipWarehouse($bom->company_id);
        $wipId = optional($wip)->id ?? $bom->company_id;
        $wipBranchId = optional($wip)->branch_id ?? $wipId;

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
