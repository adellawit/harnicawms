<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBatchStock;
use App\Models\ProductCategory;
use App\Models\ProductNature;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\ProductVariantPrice;
use App\Models\Warehouse;
use App\Services\FifoCostService;
use App\Services\Product\StockDisplayService;
use App\Services\UnitConversionService;
use App\Support\InventoryWarehouseContext;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductStockController extends Controller
{
    public function __construct(
        protected StockDisplayService $stockDisplay,
    ) {}

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
        $displayUnitMode = in_array($request->get('display_unit'), ['large', 'small'], true)
            ? $request->get('display_unit')
            : 'large';

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
                'unitConversions.fromUnit:id,name,symbol',
                'unitConversions.toUnit:id,name,symbol',
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
            ->when(
                $selectedWarehouse,
                fn ($q) => $this->applyWarehouseTypeProductFilter($q, $selectedWarehouse)
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
            ->when(
                $selectedWarehouse,
                fn ($q) => $this->applyWarehouseTypeProductFilter($q, $selectedWarehouse)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        // Get all natures and categories for filter dropdowns
        $allNatures = ProductNature::orderBy('name')->get(['id', 'name']);
        $allCategories = ProductCategory::orderBy('name')->get(['id', 'name']);

        $costBranchId = $selectedBranchId ?: auth('web')->user()->getBranchIdForTransaction();
        // HPP dari gudang terpilih — jangan campur ke FG default saat lihat gudang RM
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

        $variantStocks = $this->groupVariantStocksByUnit($stockQuery->get());

        // Prices: dari branch transaksi utama
        $pricesBranchId = $selectedBranchId ?: $this->getBranchId() ?: ($accessibleIds[0] ?? null);
        if ($pricesBranchId) {
            $variantPrices = ProductVariantPrice::where('branch_id', $pricesBranchId)
                ->get()
                ->keyBy(fn ($p) => $p->variant_id . '_' . $p->unit_id);
        }

        $batchStocksByProduct = $this->loadBatchStocksByProduct(
            $products->pluck('id')->all(),
            $warehouseId,
            $accessibleWarehouseIds
        );

        // Build product data with variants
        $productData = [];
        foreach ($products as $product) {
            $variants = $product->variants;
            $hasVariants = $variants->isNotEmpty();
            $productBatches = $batchStocksByProduct->get($product->id, collect());

            if ($hasVariants) {
                foreach ($variants as $variant) {
                    $productData[] = $this->buildVariantStockRow(
                        $product,
                        $variant,
                        $variantStocks->get($variant->id, collect()),
                        $variantPrices,
                        $costBranchId,
                        $costWarehouseId,
                        $displayUnitMode,
                        $variant === $variants->first(),
                        $variants->count(),
                        useProductSku: false,
                        batchStocks: $productBatches,
                    );
                }
            } else {
                $defaultVariant = ProductVariant::resolveForStock(
                    $product->id,
                    null,
                    auth('web')->id()
                );

                if (! $defaultVariant) {
                    continue;
                }

                $productData[] = $this->buildVariantStockRow(
                    $product,
                    $defaultVariant,
                    $variantStocks->get($defaultVariant->id, collect()),
                    $variantPrices,
                    $costBranchId,
                    $costWarehouseId,
                    $displayUnitMode,
                    true,
                    1,
                    useProductSku: true,
                    batchStocks: $productBatches,
                );
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
            'displayUnitMode' => $displayUnitMode,
        ]);
    }

    /**
     * Stok per batch & expired, dikelompokkan per product_id.
     *
     * @param  list<string>  $productIds
     * @param  list<string>  $accessibleWarehouseIds
     * @return Collection<string, Collection<int, array{batch_number: string, expiry_date: ?string, expiry_label: string, expiry_status: string, quantity: float, unit: string}>>
     */
    private function loadBatchStocksByProduct(array $productIds, ?string $warehouseId, array $accessibleWarehouseIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $rows = ProductBatchStock::query()
            ->with([
                'batch:id,product_id,batch_number,expiry_date',
                'unit:id,name,symbol',
            ])
            ->where('quantity', '>', 0)
            ->whereHas('batch', fn ($q) => $q->whereIn('product_id', $productIds)->whereNull('deleted_at'))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when(
                ! $warehouseId && ! empty($accessibleWarehouseIds),
                fn ($q) => $q->whereIn('warehouse_id', $accessibleWarehouseIds)
            )
            ->get();

        $today = now()->startOfDay();

        return $rows
            ->groupBy(fn (ProductBatchStock $row) => $row->batch?->product_id)
            ->map(function ($productRows, $productId) use ($today) {
                $product = Product::with(['unitConversions', 'defaultUnit'])->find($productId);
                $smallestUnitId = $product?->getSmallestUnitId();
                $smallestUnit = $smallestUnitId ? ProductUnit::find($smallestUnitId) : null;
                $smallestLabel = $smallestUnit?->symbol ?: ($smallestUnit?->name ?: '');

                return $productRows
                    ->groupBy(fn (ProductBatchStock $row) => ($row->product_batch_id).'|'.($row->unit_id ?? ''))
                    ->map(function ($group) use ($today, $product, $smallestUnitId, $smallestLabel) {
                        /** @var ProductBatchStock $first */
                        $first = $group->first();
                        $batch = $first->batch;
                        $expiry = $batch?->expiry_date;
                        $expiryStatus = 'none';
                        if ($expiry) {
                            $expiryDay = $expiry->copy()->startOfDay();
                            if ($expiryDay->lt($today)) {
                                $expiryStatus = 'expired';
                            } elseif ($expiryDay->lte($today->copy()->addDays(30))) {
                                $expiryStatus = 'near';
                            } else {
                                $expiryStatus = 'ok';
                            }
                        }

                        $quantity = $group->sum(fn (ProductBatchStock $r) => (float) $r->quantity);
                        $unitLabel = $first->unit?->symbol ?: ($first->unit?->name ?: '');
                        $unitId = $first->unit_id;
                        $smallestQty = null;
                        if ($product && $smallestUnitId && $unitId && $unitId !== $smallestUnitId) {
                            $smallestQty = UnitConversionService::convertQuantity($product, $quantity, $unitId, $smallestUnitId);
                        } elseif ($unitId && $smallestUnitId && $unitId === $smallestUnitId) {
                            $smallestQty = $quantity;
                        }

                        return [
                            'batch_number' => $batch?->batch_number ?: '-',
                            'expiry_date' => $expiry?->toDateString(),
                            'expiry_label' => $expiry ? $expiry->format('d/m/Y') : '-',
                            'expiry_status' => $expiryStatus,
                            'quantity' => $quantity,
                            'unit' => $unitLabel,
                            'smallest_quantity' => $smallestQty,
                            'smallest_unit' => $smallestLabel,
                        ];
                    })
                    ->filter(fn ($row) => $row['quantity'] > 0)
                    ->sortBy([
                        ['expiry_date', 'asc'],
                        ['batch_number', 'asc'],
                    ])
                    ->values();
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{unit_id: ?string, unit: mixed, quantity: float}>  $unitStockRows
     * @param  \Illuminate\Support\Collection<string, ProductVariantPrice>  $variantPrices
     */
    /**
     * @param  Collection<int, array{unit_id: ?string, unit: mixed, quantity: float}>  $unitStockRows
     * @param  \Illuminate\Support\Collection<string, ProductVariantPrice>  $variantPrices
     * @param  Collection<int, array{batch_number: string, expiry_date: ?string, expiry_label: string, expiry_status: string, quantity: float, unit: string}>  $batchStocks
     */
    private function buildVariantStockRow(
        Product $product,
        ProductVariant $variant,
        $unitStockRows,
        $variantPrices,
        ?string $costBranchId,
        ?string $costWarehouseId,
        string $displayUnitMode,
        bool $isFirstVariant,
        int $variantCount,
        bool $useProductSku = false,
        $batchStocks = null,
    ): array {
        $stockDisplay = $this->stockDisplay->build($product, $unitStockRows, $displayUnitMode);
        $unitId = $stockDisplay['unit_id'];
        $price = $unitId ? $variantPrices->get($variant->id . '_' . $unitId) : null;

        $attributes = $variant->variantAttributes->map(function ($va) {
            return $va->attributeValue?->value ?? '';
        })->filter()->implode(' / ');

        $fifoCost = ($costBranchId && $unitId)
            ? FifoCostService::currentUnitCost($variant->id, $costBranchId, $unitId, $costWarehouseId)
            : 0.0;
        $displayPurchase = $fifoCost > 0 ? $fifoCost : ($price?->purchase_price ?? $variant->purchase_price);

        // Batch tracking di level product — tampilkan di baris pertama saja agar tidak dobel.
        $showBatches = $isFirstVariant && $batchStocks && $batchStocks->isNotEmpty();

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variant_id' => $variant->id,
            'variant_name' => $attributes ?: $variant->sku,
            'sku' => $useProductSku ? $product->sku : $variant->sku,
            'barcode' => $useProductSku ? $product->barcode : $variant->barcode,
            'nature' => $product->nature?->name ?? '-',
            'category' => $product->category?->name ?? '-',
            'quantity' => $stockDisplay['quantity'],
            'unit' => $stockDisplay['unit'],
            'unit_id' => $stockDisplay['unit_id'],
            'min_stock' => $stockDisplay['min_stock'],
            'stock_by_units' => $stockDisplay['stock_by_units'],
            'show_unit_detail' => $stockDisplay['show_unit_detail'],
            'conversion_hint' => $stockDisplay['conversion_hint'],
            'conversion_chain_hint' => $stockDisplay['conversion_chain_hint'],
            'packaging_breakdown' => $stockDisplay['packaging_breakdown'],
            'packaging_hint' => $stockDisplay['packaging_hint'],
            'smallest_quantity' => $stockDisplay['smallest_quantity'],
            'smallest_unit' => $stockDisplay['smallest_unit'],
            'smallest_unit_id' => $stockDisplay['smallest_unit_id'],
            'has_smallest_display' => $stockDisplay['has_smallest_display'],
            'purchase_price' => $displayPurchase,
            'fifo_cost' => $fifoCost,
            'selling_price' => $price?->selling_price ?? $variant->selling_price,
            'is_first_variant' => $isFirstVariant,
            'variant_count' => $variantCount,
            'batch_stocks' => $showBatches ? $batchStocks->values()->all() : [],
            'has_batch_stocks' => $showBatches,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductVariantStock>  $rows
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, array{unit_id: ?string, unit: mixed, quantity: float}>>
     */
    private function groupVariantStocksByUnit($rows)
    {
        return $rows->groupBy('product_variant_id')->map(function ($variantRows) {
            return $variantRows
                ->groupBy('unit_id')
                ->map(function ($unitRows) {
                    $first = $unitRows->first();

                    return [
                        'unit_id' => $first->unit_id,
                        'unit' => $first->unit,
                        'quantity' => $unitRows->sum(fn (ProductVariantStock $row) => (float) $row->quantity),
                    ];
                })
                ->values();
        });
    }

    /**
     * Pisahkan daftar produk sesuai tipe gudang (RM vs FG) agar tidak tercampur.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Product>
     */
    private function applyWarehouseTypeProductFilter($query, Warehouse $warehouse)
    {
        $typeCode = $warehouse->warehouse_type_code;

        return match ($typeCode) {
            'RAW_MATERIAL' => $query->where(function ($q) {
                $q->whereHas('nature', fn ($n) => $n->whereIn('code', ['RAW_MATERIAL', 'SEMI_FINISHED']))
                    ->orWhereHas('itemType', fn ($t) => $t->whereIn('key', ['raw_material', 'semi_finished']));
            }),
            'FG' => $query->where(function ($q) {
                $q->whereHas('nature', fn ($n) => $n->where('code', 'FINISHED_GOOD'))
                    ->orWhereHas('itemType', fn ($t) => $t->where('key', 'finished_good'));
            }),
            // Gudang lain (quarantine, general, marketing FG-like sudah di FG):
            // tampilkan hanya item yang punya baris stok di gudang ini.
            default => $query->whereHas('stocks', function ($s) use ($warehouse) {
                $s->where('warehouse_id', $warehouse->id)->whereNull('deleted_at');
            }),
        };
    }
}
