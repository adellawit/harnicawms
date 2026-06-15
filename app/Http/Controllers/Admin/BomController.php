<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\ProductVariant;
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
        $components = WmsContext::variantOptions(); // bahan baku

        // Produk jadi yang dipilih dari daftar (terkunci); fallback ke dropdown bila kosong
        $selected = null;
        if ($request->filled('product_variant_id')) {
            $selected = ProductVariant::with('product')->find($request->query('product_variant_id'));
        }

        $outputs = WmsContext::variantOptions('FINISHED_GOOD'); // fallback dropdown produk jadi

        return view('admin.bom.create', compact('outputs', 'components', 'selected'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'product_variant_id' => ['required', 'string'],
            'output_quantity' => ['required', 'numeric', 'min:0.000001'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.variant_id' => ['required', 'string'],
            'components.*.quantity' => ['required', 'numeric', 'min:0.000001'],
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
                'output_unit_id' => $outputVariant->product->default_unit_id,
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
                    'unit_id' => $variant->product->default_unit_id,
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
        $bom = BillOfMaterial::with(['product', 'variant', 'outputUnit', 'items.componentVariant.product', 'items.unit'])
            ->findOrFail($id);

        return view('admin.bom.show', compact('bom'));
    }

    public function destroy(string $id)
    {
        $bom = BillOfMaterial::findOrFail($id);
        $bom->delete();

        return redirect()->route('bom.index')->with('success', 'BOM dihapus.');
    }
}
