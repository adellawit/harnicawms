<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductNature;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\ProductVariantPrice;
use App\Models\Warehouse;
use App\Services\FifoCostService;
use App\Support\InventoryWarehouseContext;
use App\Support\WmsContext;
use Illuminate\Http\Request;

class ProductStockController extends Controller
{
    protected function getBranchId(): ?string
    {
        return auth('web')->user()->getBranchIdForTransaction();
    }

    public function indexView(Request $request)
    {
        $user = auth('web')->user();

        // Semua BU yang dapat diakses user (untuk filter produk & stok)
        $accessibleIds = $user->getAccessibleBusinessUnitIdsForQuery();

        $ctx = InventoryWarehouseContext::resolve($request, $user, autoSelectDefault: false);
        $warehouseId = $ctx['warehouse_id'];
        $selectedWarehouse = $ctx['warehouse'];
        $selectedBranchId = $selectedWarehouse?->branch_id ?: $ctx['filter_branch_id'];
        $accessibleWarehouses = $ctx['warehouses'];
        $accessibleWarehouseIds = $accessibleWarehouses->pluck('id')->all();

        // Pagination
        $perPage = $request->get('per_page', 20);

        $fgWarehouseId = optional(WmsContext::finishedGoodsWarehouse())->id;

        $locations = $accessibleWarehouses
            ->map(function (Warehouse $warehouse) {
                $warehouse->type_code = 'WAREHOUSE';
                $type = $warehouse->warehouse_type_code ? " [{$warehouse->warehouse_type_code}]" : '';
                $warehouse->name = $warehouse->code . ' - ' . $warehouse->name . $type . ($warehouse->branch ? ' - ' . $warehouse->branch->name : '');

                return $warehouse;
            });

        // Products: tampilkan semua produk yang accessible ke user ini
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
            ->where('is_stock_item', true)
            ->when(
                $selectedBranchId && ! $selectedWarehouse,
                fn ($q) => $q->where('branch_id', $selectedBranchId),
                fn ($q) => $q->when(! empty($accessibleIds), fn ($q) => $q->whereIn('branch_id', $accessibleIds))
            )
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

        $products = $query->paginate($perPage);

        // Get all products for filter dropdown
        $allProducts = Product::where('is_stock_item', true)
            ->when(! empty($accessibleIds), fn ($q) => $q->whereIn('branch_id', $accessibleIds))
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        // Get all natures and categories for filter dropdowns
        $allNatures = ProductNature::orderBy('name')->get(['id', 'name']);
        $allCategories = ProductCategory::orderBy('name')->get(['id', 'name']);

        $costBranchId = $selectedBranchId ?: auth('web')->user()->getBranchIdForTransaction();
        $costWarehouseId = $warehouseId ?: $fgWarehouseId;
        $variantStocks = collect();
        $variantPrices = collect();

        $stockQuery = ProductVariantStock::with('unit:id,name,symbol')
            ->whereNull('deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when(
                ! $warehouseId && ! empty($accessibleWarehouseIds),
                fn ($q) => $q->whereIn('warehouse_id', $accessibleWarehouseIds)
            );

        $variantStocks = $this->aggregateVariantStocks($stockQuery->get());

        // Prices: dari branch transaksi utama
        $pricesBranchId = $selectedBranchId ?: $this->getBranchId() ?: ($accessibleIds[0] ?? null);
        if ($pricesBranchId) {
            $variantPrices = ProductVariantPrice::where('branch_id', $pricesBranchId)
                ->get()
                ->keyBy(fn ($p) => $p->variant_id . '_' . $p->unit_id);
        }

        // Build product data with variants
        $productData = [];
        foreach ($products as $product) {
            $variants = $product->variants;
            $hasVariants = $variants->isNotEmpty();

            if ($hasVariants) {
                // Product with variants - show each variant as a row
                foreach ($variants as $variant) {
                    $stock = $variantStocks->get($variant->id);
                    $unit = $stock?->unit ?? $product->defaultUnit;
                    $price = $variantPrices->get($variant->id . '_' . $unit?->id);

                    // Build variant attributes display
                    $attributes = $variant->variantAttributes->map(function ($va) {
                        return $va->attributeValue?->value ?? '';
                    })->filter()->implode(' / ');

                    $fifoCost = ($costBranchId && $unit?->id)
                        ? FifoCostService::currentUnitCost($variant->id, $costBranchId, $unit->id, $costWarehouseId)
                        : 0.0;
                    $displayPurchase = $fifoCost > 0 ? $fifoCost : ($price?->purchase_price ?? $variant->purchase_price);

                    $productData[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'variant_id' => $variant->id,
                        'variant_name' => $attributes ?: $variant->sku,
                        'sku' => $variant->sku,
                        'barcode' => $variant->barcode,
                        'nature' => $product->nature?->name ?? '-',
                        'category' => $product->category?->name ?? '-',
                        'quantity' => $stock?->quantity ?? 0,
                        'unit' => $unit?->symbol ?? $unit?->name ?? '-',
                        'unit_id' => $unit?->id,
                        'purchase_price' => $displayPurchase,
                        'fifo_cost' => $fifoCost,
                        'selling_price' => $price?->selling_price ?? $variant->selling_price,
                        'min_stock' => $product->min_stock ?? 0,
                        'is_first_variant' => $variant === $variants->first(),
                        'variant_count' => $variants->count(),
                    ];
                }
            } else {
                // Product without variants - show as single row
                $defaultVariant = ProductVariant::resolveForStock(
                    $product->id,
                    null,
                    auth('web')->id()
                );

                if (! $defaultVariant) {
                    continue;
                }

                $stock = $variantStocks->get($defaultVariant->id);
                $unit = $stock?->unit ?? $product->defaultUnit;
                $price = $variantPrices->get($defaultVariant->id . '_' . $unit?->id);

                $fifoCost = ($costBranchId && $unit?->id)
                    ? FifoCostService::currentUnitCost($defaultVariant->id, $costBranchId, $unit->id, $costWarehouseId)
                    : 0.0;
                $displayPurchase = $fifoCost > 0 ? $fifoCost : ($price?->purchase_price ?? $defaultVariant->purchase_price);

                $productData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variant_id' => $defaultVariant->id,
                    'variant_name' => null,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'nature' => $product->nature?->name ?? '-',
                    'category' => $product->category?->name ?? '-',
                    'quantity' => $stock?->quantity ?? 0,
                    'unit' => $unit?->symbol ?? $unit?->name ?? '-',
                    'unit_id' => $unit?->id,
                    'purchase_price' => $displayPurchase,
                    'fifo_cost' => $fifoCost,
                    'selling_price' => $price?->selling_price ?? $defaultVariant->selling_price,
                    'min_stock' => $product->min_stock ?? 0,
                    'is_first_variant' => true,
                    'variant_count' => 1,
                ];
            }
        }

        return view('admin.product.stock.index', [
            'products' => collect($productData),
            'paginator' => $products,
            'allProducts' => $allProducts,
            'allNatures' => $allNatures,
            'allCategories' => $allCategories,
            'locations' => $locations,
            'fgWarehouseId' => $fgWarehouseId,
            'selectedWarehouse' => $selectedWarehouse,
            'filterWarehouseId' => $warehouseId,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductVariantStock>  $rows
     * @return \Illuminate\Support\Collection<string, ProductVariantStock>
     */
    private function aggregateVariantStocks($rows)
    {
        return $rows->groupBy('product_variant_id')->map(function ($variantRows) {
            if ($variantRows->isEmpty()) {
                return null;
            }

            $totalsByUnit = $variantRows
                ->groupBy('unit_id')
                ->map(fn ($unitRows) => [
                    'quantity' => $unitRows->sum(fn (ProductVariantStock $row) => (float) $row->quantity),
                    'row' => $unitRows->first(),
                ])
                ->sortByDesc('quantity');

            $best = $totalsByUnit->first();
            $stock = $best['row'];
            $stock->quantity = $best['quantity'];

            return $stock;
        })->filter();
    }
}
