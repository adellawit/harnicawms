<?php

namespace App\Support;

use App\Models\BillOfMaterial;
use App\Models\Product;
use App\Services\UnitConversionService;

class ProductionQuantityNormalizer
{
    /**
     * Konversi qty produksi ke satuan output BOM.
     */
    public static function toBomOutputUnit(
        BillOfMaterial $bom,
        float $plannedQty,
        string $plannedUnitId
    ): float {
        $bom->loadMissing(['product.unitConversions', 'product.defaultUnit', 'outputUnit']);
        $product = $bom->product;

        if ($plannedUnitId === $bom->output_unit_id) {
            return $plannedQty;
        }

        $converted = UnitConversionService::convertQuantity(
            $product,
            $plannedQty,
            $plannedUnitId,
            $bom->output_unit_id
        );

        if ($converted === null) {
            throw new \InvalidArgumentException('Satuan produksi tidak dapat dikonversi ke satuan resep BOM.');
        }

        return $converted;
    }

    /**
     * Skala kebutuhan bahan dari qty produksi (dalam satuan pilihan user) vs output BOM.
     */
    public static function materialScale(
        BillOfMaterial $bom,
        float $plannedQty,
        string $plannedUnitId
    ): float {
        $plannedInOutputUnit = self::toBomOutputUnit($bom, $plannedQty, $plannedUnitId);
        $outputPerBatch = (float) ($bom->output_quantity ?: 1);

        return $outputPerBatch > 0 ? $plannedInOutputUnit / $outputPerBatch : $plannedInOutputUnit;
    }
}
