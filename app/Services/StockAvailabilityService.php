<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\Warehouse;

/**
 * Cek ketersediaan stok varian per gudang/cabang dengan dukungan konversi satuan.
 */
class StockAvailabilityService
{
    /**
     * Qty tersedia dalam satuan $unitId di lokasi $branchId.
     */
    public static function availableQuantity(
        ?string $variantId,
        string $branchId,
        string $unitId,
        ?string $warehouseId = null
    ): float {
        if (! $variantId) {
            return 0.0;
        }

        [$warehouseId, $operationalBranchId] = self::resolveWarehouseContext($branchId, $warehouseId);

        $rows = ProductVariantStock::query()
            ->where('product_variant_id', $variantId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId), fn ($q) => $q->where('branch_id', $operationalBranchId))
            ->whereNull('deleted_at')
            ->get();

        if ($rows->isEmpty()) {
            return 0.0;
        }

        $product = null;
        $total = 0.0;

        foreach ($rows as $stock) {
            $stockQty = (float) $stock->quantity;
            if ($stockQty <= 0) {
                continue;
            }

            if (! $stock->unit_id || $stock->unit_id === $unitId) {
                $total += $stockQty;

                continue;
            }

            $product ??= ProductVariant::with('product.unitConversions')
                ->find($variantId)
                ?->product;

            if (! $product) {
                continue;
            }

            $converted = UnitConversionService::convertQuantity($product, $stockQty, $stock->unit_id, $unitId);
            $total += $converted ?? 0.0;
        }

        return $total;
    }

    /**
     * @throws \RuntimeException jika stok tidak mencukupi
     */
    public static function assertSufficient(
        ?string $variantId,
        string $branchId,
        string $unitId,
        float $quantityNeeded,
        string $label = 'Stok',
        ?string $warehouseId = null
    ): void {
        if ($quantityNeeded <= 0) {
            return;
        }

        $available = self::availableQuantity($variantId, $branchId, $unitId, $warehouseId);

        if ($available + 1e-6 < $quantityNeeded) {
            throw new \RuntimeException(sprintf(
                '%s tidak cukup di gudang ini. Tersedia: %s, dibutuhkan: %s.',
                $label,
                self::formatQty($available),
                self::formatQty($quantityNeeded)
            ));
        }
    }

    protected static function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    protected static function resolveWarehouseContext(string $branchId, ?string $warehouseId): array
    {
        $warehouse = null;

        if ($warehouseId) {
            $warehouse = Warehouse::query()->whereKey($warehouseId)->first();
        }

        if (! $warehouse) {
            $warehouse = Warehouse::query()
                ->where('id', $branchId)
                ->orWhere('legacy_business_unit_id', $branchId)
                ->first();
        }

        if (! $warehouse) {
            $warehouse = Warehouse::defaultForBranch($branchId);
        }

        if (! $warehouse) {
            return [null, $branchId];
        }

        return [$warehouse->id, $warehouse->branch_id ?: $branchId];
    }
}
