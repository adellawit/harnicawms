<?php

namespace App\Support;

use App\Models\Product;
use App\Services\Manufacturing\ProductionSimulationService;
use App\Services\UnitConversionService;

class ProductionQuantityDisplay
{
    /**
     * Keterangan konversi ke satuan terbesar (default unit produk).
     */
    public static function largestUnitHint(Product $product, float $qty, string $unitId): ?string
    {
        $product->loadMissing(['unitConversions', 'defaultUnit']);

        $largestUnitId = $product->default_unit_id;
        if (! $largestUnitId || $unitId === $largestUnitId) {
            return null;
        }

        $chain = ProductionSimulationService::buildUnitChain($product);
        if ($chain->isEmpty()) {
            return null;
        }

        $largestUnit = $chain->first()['unit'];
        $largestLabel = $largestUnit->symbol ?: $largestUnit->name;

        $converted = UnitConversionService::convertQuantity($product, $qty, $unitId, $largestUnitId);
        if ($converted === null) {
            return null;
        }

        return '≈ '.self::formatQty($converted).' '.$largestLabel;
    }

    /**
     * Ringkasan pecahan kemasan (krt · pack · box · sct) dari qty dalam satuan apa pun.
     */
    public static function breakdownHint(Product $product, float $qty, string $unitId): ?string
    {
        $rows = self::packagingBreakdown($product, $qty, $unitId);
        if ($rows === []) {
            return null;
        }

        if (count($rows) === 1 && ($rows[0]['unit_id'] ?? null) === $unitId) {
            return null;
        }

        return implode(' · ', collect($rows)
            ->map(fn (array $row) => self::formatQty($row['qty']).' '.$row['label'])
            ->all());
    }

    /**
     * Pecahan kemasan fisik (karton + pack + box + sachet) dari qty.
     *
     * @return array<int, array{unit_id: string, label: string, qty: float}>
     */
    public static function packagingBreakdown(Product $product, float $qty, string $unitId): array
    {
        $product->loadMissing(['unitConversions', 'defaultUnit']);

        $smallestUnitId = $product->getSmallestUnitId();
        if (! $smallestUnitId || $qty <= 0) {
            return [];
        }

        $smallestQty = UnitConversionService::convertQuantity($product, $qty, $unitId, $smallestUnitId);
        if ($smallestQty === null || $smallestQty <= 0) {
            return [];
        }

        return ProductionSimulationService::decomposeSmallestQuantity($product, $smallestQty);
    }

    /**
     * Qty setara di setiap level rantai satuan (krt / pack / box / sachet).
     *
     * @return array<int, array{unit_id: string, label: string, qty: float, is_base: bool}>
     */
    public static function qtyLevelBreakdown(Product $product, float $qty, string $unitId): array
    {
        $product->loadMissing(['unitConversions', 'defaultUnit']);

        if ($qty <= 0) {
            return [];
        }

        $chain = ProductionSimulationService::buildUnitChain($product);
        if ($chain->isEmpty()) {
            return [];
        }

        return $chain->map(function (array $row) use ($product, $qty, $unitId) {
            $targetId = $row['unit']->id;
            $converted = $targetId === $unitId
                ? $qty
                : UnitConversionService::convertQuantity($product, $qty, $unitId, $targetId);

            if ($converted === null) {
                return null;
            }

            return [
                'unit_id' => $targetId,
                'label' => $row['unit']->symbol ?: $row['unit']->name,
                'qty' => (float) $converted,
                'is_base' => $targetId === $unitId,
            ];
        })->filter()->values()->all();
    }

    /**
     * HPP/unit di setiap level rantai satuan, dari cost per $unitId.
     *
     * @return array<int, array{unit_id: string, label: string, unit_cost: float, is_base: bool}>
     */
    public static function unitCostLevelBreakdown(Product $product, float $unitCost, string $unitId): array
    {
        $product->loadMissing(['unitConversions', 'defaultUnit']);

        if ($unitCost <= 0) {
            return [];
        }

        $chain = ProductionSimulationService::buildUnitChain($product);
        if ($chain->isEmpty()) {
            return [];
        }

        return $chain->map(function (array $row) use ($product, $unitCost, $unitId) {
            $targetId = $row['unit']->id;

            if ($targetId === $unitId) {
                $cost = $unitCost;
            } else {
                // 1 base unit = $factor target units → cost_per_target = cost_per_base / factor
                $factor = UnitConversionService::convertQuantity($product, 1.0, $unitId, $targetId);
                if ($factor === null || abs($factor) < 1e-12) {
                    return null;
                }
                $cost = $unitCost / $factor;
            }

            return [
                'unit_id' => $targetId,
                'label' => $row['unit']->symbol ?: $row['unit']->name,
                'unit_cost' => round($cost, 4),
                'is_base' => $targetId === $unitId,
            ];
        })->filter()->values()->all();
    }

    /**
     * Gabungan hint untuk ditampilkan di UI (prioritas: konversi ke satuan terbesar, lalu pecahan).
     */
    public static function conversionSummary(Product $product, float $qty, string $unitId): ?string
    {
        return self::largestUnitHint($product, $qty, $unitId)
            ?? self::breakdownHint($product, $qty, $unitId);
    }

    protected static function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 4, ',', '.'), '0'), ',');
    }
}
