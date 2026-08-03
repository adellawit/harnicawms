<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner\CuttingPriceConfig;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\DataTables;

class CuttingPriceConfigController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->get('status', '');
        $isFilter = $status !== '';

        return view('admin.partner.cutting-price-config.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $data = CuttingPriceConfig::query()
            ->select('partner.cutting_price_configs.*')
            ->with([
                'product:id,code,name',
                'category:id,code,name',
            ]);

        if ($request->get('status') === 'active') {
            // default non-trashed
        } elseif ($request->get('status') === 'deleted') {
            $data->onlyTrashed();
        } else {
            $data->withTrashed();
        }

        $data->orderBy('sort_order')->orderByDesc('created_at');

        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->addColumn('product_label', function (CuttingPriceConfig $row) {
                $p = $row->product;

                return $p ? trim(($p->code ?? '').' · '.($p->name ?? '')) : '-';
            })
            ->addColumn('category_label', function (CuttingPriceConfig $row) {
                $c = $row->category;

                return $c ? ($c->code ?: $c->name) : '-';
            })
            ->filter(function ($query) use ($request) {
                $search = $request->input('search.value');
                if (! $search) {
                    return;
                }
                $query->where(function ($q) use ($search) {
                    $q->where('unit_code', 'ILIKE', "%{$search}%")
                        ->orWhereHas('product', function ($p) use ($search) {
                            $p->where('code', 'ILIKE', "%{$search}%")
                                ->orWhere('name', 'ILIKE', "%{$search}%");
                        })
                        ->orWhereHas('category', function ($c) use ($search) {
                            $c->where('code', 'ILIKE', "%{$search}%")
                                ->orWhere('name', 'ILIKE', "%{$search}%");
                        });
                });
            })
            ->toJson();
    }

    public function insertView()
    {
        return view('admin.partner.cutting-price-config.insert', [
            'products' => $this->productOptions(),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function insertData(Request $request)
    {
        $validated = $this->validatePayload($request);

        CuttingPriceConfig::create(array_merge($validated, [
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]));

        return redirect()
            ->route('partner.cutting-price-config.index.view')
            ->with('success', 'Cutting price config berhasil ditambahkan.');
    }

    public function editView(string $id)
    {
        $config = CuttingPriceConfig::withTrashed()->findOrFail($id);

        return view('admin.partner.cutting-price-config.edit', [
            'config' => $config,
            'products' => $this->productOptions(),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function editData(Request $request)
    {
        $config = CuttingPriceConfig::withTrashed()->findOrFail($request->input('id'));
        $validated = $this->validatePayload($request, $config->id);

        $config->update(array_merge($validated, [
            'updated_by' => auth('web')->id(),
        ]));

        return redirect()
            ->route('partner.cutting-price-config.index.view')
            ->with('success', 'Cutting price config berhasil diperbarui.');
    }

    public function deleteData(Request $request)
    {
        $request->validate(['cutting_price_config_id_deleted' => 'required|uuid']);

        $config = CuttingPriceConfig::findOrFail($request->input('cutting_price_config_id_deleted'));
        $config->deleted_by = auth('web')->id();
        $config->save();
        $config->delete();

        return redirect()
            ->route('partner.cutting-price-config.index.view')
            ->with('success', 'Cutting price config berhasil dihapus.');
    }

    public function restoreData(Request $request)
    {
        $request->validate(['cutting_price_config_id_restored' => 'required|uuid']);

        $config = CuttingPriceConfig::onlyTrashed()->findOrFail($request->input('cutting_price_config_id_restored'));
        $config->restore();
        $config->deleted_by = null;
        $config->updated_by = auth('web')->id();
        $config->save();

        return redirect()
            ->route('partner.cutting-price-config.index.view')
            ->with('success', 'Cutting price config berhasil direstore.');
    }

    private function validatePayload(Request $request, ?string $ignoreId = null): array
    {
        $validated = $request->validate([
            'product_id' => ['required', 'uuid', Rule::exists('product.products', 'id')],
            'category_id' => ['nullable', 'uuid', Rule::exists('product.product_categories', 'id')],
            'unit_code' => 'required|string|max:20',
            'official_price' => 'required',
            'map_price' => 'required',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $official = normalize_number_input($request->input('official_price'));
        $map = normalize_number_input($request->input('map_price'));

        if ($official === null || $official < 0) {
            throw ValidationException::withMessages([
                'official_price' => 'Harga resmi harus angka ≥ 0.',
            ]);
        }
        if ($map === null || $map < 0) {
            throw ValidationException::withMessages([
                'map_price' => 'MAP harus angka ≥ 0.',
            ]);
        }
        if ($map > $official) {
            throw ValidationException::withMessages([
                'map_price' => 'MAP tidak boleh lebih tinggi dari harga resmi.',
            ]);
        }

        $product = Product::query()->findOrFail($validated['product_id']);
        $categoryId = $validated['category_id'] ?: $product->category_id;

        if (! $categoryId) {
            throw ValidationException::withMessages([
                'category_id' => 'Pilih kategori, atau set kategori pada produk terlebih dahulu.',
            ]);
        }

        $unitCode = strtoupper(trim($validated['unit_code']));

        $dup = CuttingPriceConfig::query()
            ->where('product_id', $validated['product_id'])
            ->where('unit_code', $unitCode)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($dup) {
            throw ValidationException::withMessages([
                'unit_code' => 'Config untuk produk + unit ini sudah ada.',
            ]);
        }

        return [
            'product_id' => $validated['product_id'],
            'category_id' => $categoryId,
            'unit_code' => $unitCode,
            'official_price' => $official,
            'map_price' => $map,
            'sort_order' => (int) ($validated['sort_order'] ?? 10),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function productOptions()
    {
        return Product::query()
            ->whereNull('deleted_at')
            ->where('is_sale_item', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'category_id']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProductCategory>
     */
    private function categoryOptions()
    {
        return ProductCategory::query()
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }
}
