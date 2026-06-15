<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductNature;
use App\Models\ProductPriceList;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Services\FifoCostService;
use App\Support\WmsContext;
use Illuminate\Http\Request;

class ProductPriceController extends Controller
{
    protected function getBranchId(): ?string
    {
        return auth('web')->user()->current_business_unit_id;
    }

    protected function getCompanyId(): ?string
    {
        return auth('web')->user()->getCompanyIdForProduct();
    }

    public function indexView(Request $request)
    {
        $defaultBranchId = $this->getBranchId();
        $branchId = $request->get('branch_id', $defaultBranchId);
        $companyId = $this->getCompanyId();
        $perPage = $request->get('per_page', 20);
        $priceListId = $request->get('price_list_id', '');

        // Price lists for filter
        $priceLists = ProductPriceList::whereNull('deleted_at')
            ->where('is_active', true)
            ->forBusinessContext($companyId, $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $query = Product::with([
                'defaultUnit:id,name,symbol',
                'nature:id,name',
                'category:id,name',
                'variants' => function ($q) {
                    $q->with(['variantAttributes.attributeValue.attributeDefinition:id,name'])
                        ->where('is_active', true)
                        ->orderBy('sort_order');
                },
            ])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name');

        if ($request->filled('product_id')) {
            $query->where('id', $request->product_id);
        }
        if ($request->filled('sku')) {
            $query->where('sku', $request->sku);
        }
        if ($request->filled('nature_id')) {
            $query->where('nature_id', $request->nature_id);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('variant_search')) {
            $variantSearch = $request->variant_search;
            $query->whereHas('variants', function ($q) use ($variantSearch) {
                $q->where('sku', 'like', "%{$variantSearch}%");
            });
        }

        $paginator = $query->paginate($perPage);

        $allProducts = Product::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        $allNatures = ProductNature::orderBy('name')->get(['id', 'name']);
        $allCategories = ProductCategory::orderBy('name')->get(['id', 'name']);

        $costBranchIds = $this->costBranchIds($companyId, $branchId);

        $variantPrices = collect();
        $baseVariantPrices = collect();
        if ($branchId) {
            $priceQuery = ProductVariantPrice::where('branch_id', $branchId);
            if ($priceListId === '' || $priceListId === null) {
                $priceQuery->whereNull('price_list_id');
            } else {
                $priceQuery->where('price_list_id', $priceListId);
            }
            $variantPrices = $priceQuery->get()
                ->keyBy(fn ($p) => $p->variant_id . '_' . $p->unit_id);
        }

        if ($costBranchIds->isNotEmpty()) {
            $baseVariantPrices = ProductVariantPrice::query()
                ->whereNull('price_list_id')
                ->whereIn('branch_id', $costBranchIds)
                ->get()
                ->groupBy(fn ($p) => $p->variant_id . '_' . $p->unit_id)
                ->map(fn ($rows) => $rows->first(fn ($r) => (float) $r->purchase_price > 0) ?? $rows->first());
        }

        $isBasePriceList = $priceListId === '' || $priceListId === null;

        $productData = [];
        foreach ($paginator as $product) {
            $variants = $product->variants;
            $hasVariants = $variants->isNotEmpty();

            if ($hasVariants) {
                foreach ($variants as $variant) {
                    $unit = $product->defaultUnit;
                    $price = $variantPrices->get($variant->id . '_' . $unit?->id);

                    $attributes = $variant->variantAttributes->map(function ($va) {
                        return $va->attributeValue?->value ?? '';
                    })->filter()->implode(' / ');

                    $hpp = $this->resolveVariantHpp(
                        $variant->id,
                        $unit?->id ?? $product->default_unit_id,
                        $costBranchIds,
                        $baseVariantPrices,
                        $variant
                    );

                    $productData[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'variant_id' => $variant->id,
                        'variant_name' => $attributes ?: $variant->sku,
                        'sku' => $variant->sku,
                        'nature' => $product->nature?->name ?? '-',
                        'category' => $product->category?->name ?? '-',
                        'unit' => $unit?->symbol ?? $unit?->name ?? '-',
                        'unit_id' => $unit?->id ?? $product->default_unit_id,
                        'fifo_cost' => $hpp['fifo_cost'],
                        'hpp' => $hpp['display'],
                        'purchase_price' => $isBasePriceList
                            ? (float) ($price?->purchase_price ?? $hpp['display'] ?? $variant->purchase_price ?? 0)
                            : (float) ($price?->purchase_price ?? 0),
                        'selling_price' => (float) ($price?->selling_price ?? $variant->selling_price ?? 0),
                        'is_first_variant' => $variant === $variants->first(),
                        'variant_count' => $variants->count(),
                    ];
                }
            } else {
                $defaultVariant = $variants->first() ?? $product->variants()->first();
                if (!$defaultVariant) {
                    $defaultVariant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $product->sku ?? 'PROD-' . substr($product->id, 0, 8),
                        'is_active' => true,
                        'created_by' => auth('web')->id(),
                    ]);
                }

                $unit = $product->defaultUnit;
                $price = $variantPrices->get($defaultVariant->id . '_' . $unit?->id);

                $hpp = $this->resolveVariantHpp(
                    $defaultVariant->id,
                    $unit?->id ?? $product->default_unit_id,
                    $costBranchIds,
                    $baseVariantPrices,
                    $defaultVariant
                );

                $productData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variant_id' => $defaultVariant->id,
                    'variant_name' => null,
                    'sku' => $product->sku,
                    'nature' => $product->nature?->name ?? '-',
                    'category' => $product->category?->name ?? '-',
                    'unit' => $unit?->symbol ?? $unit?->name ?? '-',
                    'unit_id' => $unit?->id ?? $product->default_unit_id,
                    'fifo_cost' => $hpp['fifo_cost'],
                    'hpp' => $hpp['display'],
                    'purchase_price' => $isBasePriceList
                        ? (float) ($price?->purchase_price ?? $hpp['display'] ?? $defaultVariant->purchase_price ?? 0)
                        : (float) ($price?->purchase_price ?? 0),
                    'selling_price' => (float) ($price?->selling_price ?? $defaultVariant->selling_price ?? 0),
                    'is_first_variant' => true,
                    'variant_count' => 1,
                ];
            }
        }

        return view('admin.product.price.index', [
            'products' => collect($productData),
            'paginator' => $paginator,
            'allProducts' => $allProducts,
            'allNatures' => $allNatures,
            'allCategories' => $allCategories,
            'priceLists' => $priceLists,
            'filterPriceListId' => $priceListId,
            'isBasePriceList' => $isBasePriceList,
        ]);
    }

    /**
     * Cabang/gudang yang dipakai untuk lookup HPP FIFO & harga beli base.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function costBranchIds(?string $companyId, ?string $branchId)
    {
        return collect([
            optional(WmsContext::finishedGoodsWarehouse($companyId))->id,
            optional(WmsContext::wipWarehouse($companyId))->id,
            $branchId,
        ])->filter()->unique()->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $costBranchIds
     * @param  \Illuminate\Support\Collection<string, ProductVariantPrice>  $baseVariantPrices
     * @return array{fifo_cost: float, display: float}
     */
    protected function resolveVariantHpp(
        string $variantId,
        ?string $unitId,
        $costBranchIds,
        $baseVariantPrices,
        ProductVariant $variant
    ): array {
        $fifoCost = 0.0;

        if ($unitId) {
            foreach ($costBranchIds as $costBranchId) {
                $cost = FifoCostService::currentUnitCost($variantId, $costBranchId, $unitId);
                if ($cost > 0) {
                    $fifoCost = $cost;
                    break;
                }
            }
        }

        $basePrice = $baseVariantPrices->get($variantId . '_' . $unitId);
        $avgPurchase = (float) ($basePrice?->purchase_price ?? $variant->purchase_price ?? 0);
        $display = $fifoCost > 0 ? $fifoCost : $avgPurchase;

        return [
            'fifo_cost' => $fifoCost,
            'display' => $display,
        ];
    }

    public function saveData(Request $request)
    {
        $branchId = $this->getBranchId();
        $companyId = $this->getCompanyId();
        if (!$branchId) {
            return response()->json(['success' => false, 'message' => 'No branch assigned.'], 422);
        }

        $request->merge([
            'price_list_id' => $request->filled('price_list_id') ? $request->price_list_id : null,
        ]);

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product.product_variants,id',
            'items.*.product_id' => 'required|exists:product.products,id',
            'items.*.unit_id' => 'required',
            'items.*.purchase_price' => 'nullable|string',
            'items.*.selling_price' => 'nullable|string',
            'price_list_id' => 'nullable|exists:product.product_price_lists,id',
        ]);

        $priceListId = $request->get('price_list_id');
        $userId = auth('web')->id();
        $saved = 0;

        foreach ($request->items as $item) {
            $sp = normalize_number_input($item['selling_price'] ?? null);
            $variantId = $item['variant_id'];
            $unitId = $item['unit_id'];

            $priceQuery = ProductVariantPrice::withTrashed()
                ->where('variant_id', $variantId)
                ->where('branch_id', $branchId)
                ->where('unit_id', $unitId);

            if ($priceListId) {
                $priceQuery->where('price_list_id', $priceListId);
            } else {
                $priceQuery->whereNull('price_list_id');
            }

            $price = $priceQuery->first();

            if ($priceListId) {
                if ($sp === null || $sp === '') {
                    continue;
                }

                $payload = [
                    'company_id' => $companyId,
                    'selling_price' => $sp,
                    'updated_by' => $userId,
                    'deleted_by' => null,
                ];

                if ($price) {
                    if ($price->trashed()) {
                        $price->restore();
                    }
                    $price->update($payload);
                } else {
                    ProductVariantPrice::create(array_merge($payload, [
                        'variant_id' => $variantId,
                        'branch_id' => $branchId,
                        'unit_id' => $unitId,
                        'price_list_id' => $priceListId,
                        'purchase_price' => 0,
                        'created_by' => $userId,
                    ]));
                }

                $saved++;
                continue;
            }

            $pp = normalize_number_input($item['purchase_price'] ?? null) ?? 0;
            if ($pp < 0) {
                continue;
            }

            if ($price) {
                if ($price->trashed()) {
                    $price->restore();
                }
                $price->update([
                    'company_id' => $companyId,
                    'purchase_price' => $pp,
                    'selling_price' => $sp,
                    'updated_by' => $userId,
                    'deleted_by' => null,
                ]);
            } else {
                ProductVariantPrice::create([
                    'variant_id' => $variantId,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'unit_id' => $unitId,
                    'price_list_id' => null,
                    'purchase_price' => $pp,
                    'selling_price' => $sp,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $saved++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$saved} price(s) saved successfully.",
        ]);
    }
}
