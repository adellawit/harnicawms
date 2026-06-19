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

        // Lokasi spesifik jika dipilih lewat filter. Parameter lama tetap bernama branch_id.
        $locationId = $request->get('branch_id');

        // Pagination
        $perPage = $request->get('per_page', 20);

        $fgWarehouseId = optional(WmsContext::finishedGoodsWarehouse())->id;
        $selectedWarehouse = $locationId ? Warehouse::with('branch')->find($locationId) : null;
        $warehouseId = $selectedWarehouse?->id;
        $selectedBranchId = $selectedWarehouse?->branch_id ?: $locationId;

        $warehouseQuery = Warehouse::query()
            ->with('branch:id,name')
            ->inventoryActive()
            ->when(! empty($accessibleIds), function ($q) use ($accessibleIds) {
                $q->where(function ($inner) use ($accessibleIds) {
                    $inner->whereIn('branch_id', $accessibleIds)
                        ->orWhereHas('assignedBranches', fn ($assigned) => $assigned->whereIn('master_data.business_units.id', $accessibleIds));
                });
            })
            ->orderBy('code');

        $locations = $warehouseQuery->get(['id', 'name', 'code', 'branch_id', 'warehouse_type_code'])
            ->map(function (Warehouse $warehouse) {
                $warehouse->type_code = 'WAREHOUSE';
                $warehouse->name = $warehouse->name . ($warehouse->branch ? ' - ' . $warehouse->branch->name : '');
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
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when(! $warehouseId && ! empty($accessibleIds), fn ($q) => $q->whereIn('branch_id', $accessibleIds));

        // Aggregate quantity per variant (sum across branches if no specific branch selected)
        $variantStocks = $stockQuery->get()
            ->groupBy('product_variant_id')
            ->map(function ($rows) {
                $first = $rows->first();
                $first->quantity = $rows->sum('quantity');
                return $first;
            });

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
                // Find or create default variant
                $defaultVariant = $variants->first() ?? $product->variants()->first();
                if (!$defaultVariant) {
                    // Create a default variant for products without one
                    $defaultVariant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $product->sku ?? 'PROD-' . substr($product->id, 0, 8),
                        'barcode' => $product->barcode ?? substr($product->id, 0, 13),
                        'purchase_price' => 0,
                        'selling_price' => 0,
                        'is_active' => true,
                        'created_by' => auth('web')->id(),
                    ]);
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
        ]);
    }
}
