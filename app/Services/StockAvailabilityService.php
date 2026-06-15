<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\ProductVariantStock;

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
        string $unitId
    ): float {
        if (! $variantId) {
            return 0.0;
        }

        $stock = ProductVariantStock::query()
            ->where('product_variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->first();

        if (! $stock) {
            return 0.0;
        }

        $stockQty = (float) $stock->quantity;
        if ($stockQty <= 0) {
            return 0.0;
        }

        if ($stock->unit_id === $unitId) {
            return $stockQty;
        }

        $product = ProductVariant::with('product.unitConversions')
            ->find($variantId)
            ?->product;

        if (! $product) {
            return 0.0;
        }

        return UnitConversionService::convertQuantity($product, $stockQty, $stock->unit_id, $unitId) ?? 0.0;
    }

    /**
     * @throws \RuntimeException jika stok tidak mencukupi
     */
    public static function assertSufficient(
        ?string $variantId,
        string $branchId,
        string $unitId,
        float $quantityNeeded,
        string $label = 'Stok'
    ): void {
        if ($quantityNeeded <= 0) {
            return;
        }

        $available = self::availableQuantity($variantId, $branchId, $unitId);

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
}
