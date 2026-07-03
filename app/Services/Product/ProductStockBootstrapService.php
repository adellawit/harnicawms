<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\Warehouse;
use App\Support\WmsContext;

/**
 * Bootstrap product_variant_stock rows (qty 0) when a stock-tracked product is created.
 *
 * One warehouse per product, resolved by item type → warehouse type, with branch default fallback.
 */
class ProductStockBootstrapService
{
    /** @var array<string, string|null> */
    private const ITEM_TYPE_WAREHOUSE_MAP = [
        'raw_material' => 'RAW_MATERIAL',
        'semi_finished' => 'RAW_MATERIAL',
        'finished_good' => 'FG',
        'service' => null,
        'bundle' => null,
    ];

    /** @var array<string, string|null> */
    private const LEGACY_NATURE_WAREHOUSE_MAP = [
        'RAW_MATERIAL' => 'RAW_MATERIAL',
        'SEMI_FINISHED' => 'RAW_MATERIAL',
        'FINISHED_GOOD' => 'FG',
        'SERVICE' => null,
        'NON_STOCK' => null,
    ];

    public function bootstrap(Product $product, ?string $userId = null): int
    {
        if (! $this->shouldBootstrap($product)) {
            return 0;
        }

        $branchId = $product->branch_id;
        if (! $branchId) {
            return 0;
        }

        $warehouse = $this->resolveWarehouse($product, $branchId);
        if (! $warehouse) {
            return 0;
        }

        $product->loadMissing([
            'variants' => fn ($q) => $q->whereNull('deleted_at'),
            'unitConversions' => fn ($q) => $q->whereNull('deleted_at'),
        ]);

        $unitId = $product->getSmallestUnitId();
        if (! $unitId) {
            return 0;
        }

        $created = 0;
        foreach ($product->variants as $variant) {
            if ($this->ensureVariantStock($product, $variant, $warehouse, $unitId, $userId)) {
                $created++;
            }
        }

        return $created;
    }

    public function shouldBootstrap(Product $product): bool
    {
        if (! $product->is_stock_item) {
            return false;
        }

        $product->loadMissing('productNature');

        return ($product->productNature?->key ?? 'inventory') !== 'non_inventory';
    }

    public function resolveWarehouse(Product $product, string $branchId): ?Warehouse
    {
        $typeCode = $this->resolveWarehouseTypeCode($product);

        if ($typeCode) {
            $warehouse = Warehouse::inventoryActive()
                ->where('branch_id', $branchId)
                ->where('warehouse_type_code', $typeCode)
                ->orderByDesc('is_default')
                ->orderBy('code')
                ->first();

            if ($warehouse) {
                return $warehouse;
            }
        }

        return WmsContext::defaultWarehouse($branchId);
    }

    protected function resolveWarehouseTypeCode(Product $product): ?string
    {
        $product->loadMissing(['itemType', 'nature']);

        $itemTypeKey = $product->itemType?->key;
        if ($itemTypeKey !== null && array_key_exists($itemTypeKey, self::ITEM_TYPE_WAREHOUSE_MAP)) {
            return self::ITEM_TYPE_WAREHOUSE_MAP[$itemTypeKey];
        }

        $legacyNatureCode = $product->nature?->code;
        if ($legacyNatureCode !== null && array_key_exists($legacyNatureCode, self::LEGACY_NATURE_WAREHOUSE_MAP)) {
            return self::LEGACY_NATURE_WAREHOUSE_MAP[$legacyNatureCode];
        }

        return 'FG';
    }

    protected function ensureVariantStock(
        Product $product,
        ProductVariant $variant,
        Warehouse $warehouse,
        string $unitId,
        ?string $userId
    ): bool {
        $stock = ProductVariantStock::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('deleted_at')
            ->first();

        if ($stock) {
            return false;
        }

        ProductVariantStock::create([
            'product_variant_id' => $variant->id,
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'branch_id' => $product->branch_id,
            'warehouse_id' => $warehouse->id,
            'unit_id' => $unitId,
            'quantity' => 0,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return true;
    }
}
