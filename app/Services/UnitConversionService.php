<?php

namespace App\Services;

use App\Models\Product;

/**
 * Konversi qty & HPP antar satuan produk (product_unit_conversions).
 */
class UnitConversionService
{
    public static function convertQuantity(Product $product, float $quantity, string $fromUnitId, string $toUnitId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return $quantity;
        }

        return $product->convertQuantity($quantity, $fromUnitId, $toUnitId);
    }

    /**
     * Konversi harga per satuan: mis. Rp/Box → Rp/Sct bila 1 Box = 3 Sct.
     */
    public static function convertUnitCost(Product $product, float $unitCost, string $fromUnitId, string $toUnitId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return round($unitCost, 4);
        }

        $factor = self::convertQuantity($product, 1.0, $fromUnitId, $toUnitId);
        if ($factor === null || $factor <= 0) {
            return null;
        }

        return round($unitCost / $factor, 4);
    }
}
