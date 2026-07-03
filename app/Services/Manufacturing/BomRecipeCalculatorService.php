<?php

namespace App\Services\Manufacturing;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\UnitConversionService;

/**
 * Kalkulator kebutuhan bahan BOM berdasarkan satuan terkecil (sachet/pcs).
 * Asumsi: 1 satuan terkecil FG membutuhkan 1 satuan terkecil bahan baku.
 */
class BomRecipeCalculatorService
{
    /**
     * @return array<string, mixed>|null
     */
    public static function suggest(
        ProductVariant $outputVariant,
        float $outputQty,
        string $outputUnitId,
        ProductVariant $componentVariant,
        string $componentUnitId
    ): ?array {
        $outputProduct = $outputVariant->product;
        $componentProduct = $componentVariant->product;

        if (! $outputProduct || ! $componentProduct) {
            return null;
        }

        $outputProduct->loadMissing(['unitConversions', 'defaultUnit']);
        $componentProduct->loadMissing(['unitConversions', 'defaultUnit']);

        $fgSmallestId = $outputProduct->getSmallestUnitId();
        $rmSmallestId = $componentProduct->getSmallestUnitId();

        if (! $fgSmallestId || ! $rmSmallestId) {
            return null;
        }

        $fgSmallestQty = UnitConversionService::convertQuantity(
            $outputProduct,
            $outputQty,
            $outputUnitId,
            $fgSmallestId
        );

        if ($fgSmallestQty === null || $fgSmallestQty <= 0) {
            return null;
        }

        $componentQty = UnitConversionService::convertQuantity(
            $componentProduct,
            $fgSmallestQty,
            $rmSmallestId,
            $componentUnitId
        );

        if ($componentQty === null || $componentQty <= 0) {
            return null;
        }

        $fgSmallestUnit = ProductionSimulationService::buildUnitChain($outputProduct)
            ->last()['unit'] ?? null;
        $rmSmallestUnit = ProductionSimulationService::buildUnitChain($componentProduct)
            ->last()['unit'] ?? null;
        $componentUnit = ProductionSimulationService::buildUnitChain($componentProduct)
            ->first(fn (array $row) => $row['unit']->id === $componentUnitId)['unit'] ?? null;

        $outputUnit = ProductionSimulationService::buildUnitChain($outputProduct)
            ->first(fn (array $row) => $row['unit']->id === $outputUnitId)['unit'] ?? null;

        return [
            'output_qty' => $outputQty,
            'output_unit' => $outputUnit?->symbol ?: $outputUnit?->name ?: '',
            'output_smallest_qty' => round($fgSmallestQty, 4),
            'output_smallest_unit' => $fgSmallestUnit?->symbol ?: $fgSmallestUnit?->name ?: '',
            'suggested_qty' => round($componentQty, 6),
            'component_unit' => $componentUnit?->symbol ?: $componentUnit?->name ?: '',
            'component_unit_id' => $componentUnitId,
            'hint' => sprintf(
                '%s %s FG (= %s %s) → butuh %s %s bahan (basis 1:1 %s)',
                rtrim(rtrim(number_format($outputQty, 4, '.', ''), '0'), '.'),
                $outputUnit?->symbol ?: $outputUnit?->name ?: 'unit',
                rtrim(rtrim(number_format($fgSmallestQty, 4, '.', ''), '0'), '.'),
                $fgSmallestUnit?->symbol ?: $fgSmallestUnit?->name ?: 'unit',
                rtrim(rtrim(number_format($componentQty, 4, '.', ''), '0'), '.'),
                $componentUnit?->symbol ?: $componentUnit?->name ?: 'unit',
                $fgSmallestUnit?->symbol ?: $fgSmallestUnit?->name ?: 'unit'
            ),
        ];
    }
}
