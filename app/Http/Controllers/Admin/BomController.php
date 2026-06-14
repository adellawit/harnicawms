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
    public function index()
    {
        $boms = BillOfMaterial::with(['product', 'variant', 'items'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.bom.index', compact('boms'));
    }

    public function create()
    {
        $outputs = WmsContext::variantOptions(); // produk jadi/semi
        $components = WmsContext::variantOptions(); // bahan baku

        return view('admin.bom.create', compact('outputs', 'components'));
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
