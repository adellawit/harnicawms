<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Services\UnitConversionService;
use Illuminate\Support\Collection;

class StockDisplayService
{
    /**
     * @param  Collection<int, array{unit_id: ?string, unit: mixed, quantity: float}>  $unitStockRows
     * @return array{
     *   quantity: float,
     *   unit: string,
     *   unit_id: ?string,
     *   min_stock: float,
     *   stock_by_units: array<int, array{unit_id: ?string, unit: string, quantity: float}>,
     *   show_unit_detail: bool,
     *   conversion_hint: ?string
     * }
     */
    public function build(Product $product, Collection $unitStockRows, string $displayUnitMode = 'large'): array
    {
        $product->loadMissing(['defaultUnit', 'unitConversions.fromUnit', 'unitConversions.toUnit']);

        $largeUnitId = $product->default_unit_id;
        $smallUnitId = $product->getSmallestUnitId();
        $units = $product->getBarcodeUnits();

        $stockByUnits = $unitStockRows
            ->map(function ($row) {
                $unit = $row['unit'] ?? null;

                return [
                    'unit_id' => $row['unit_id'] ?? null,
                    'unit' => $unit?->symbol ?? $unit?->name ?? '-',
                    'quantity' => (float) ($row['quantity'] ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['unit_id'] !== null)
            ->values()
            ->all();

        $displayUnitId = $displayUnitMode === 'small' ? $smallUnitId : $largeUnitId;
        $displayUnit = $units->firstWhere('id', $displayUnitId) ?? $product->defaultUnit;

        $totalSmallest = $this->sumInUnit($product, $stockByUnits, $smallUnitId);

        if ($displayUnitMode === 'small') {
            $displayQty = $totalSmallest;
        } else {
            $displayQty = $largeUnitId && $smallUnitId && $largeUnitId !== $smallUnitId
                ? (UnitConversionService::convertQuantity($product, $totalSmallest, $smallUnitId, $largeUnitId) ?? $totalSmallest)
                : $totalSmallest;
        }

        $minStockBase = (float) ($product->min_stock ?? 0);
        $minStock = $minStockBase;

        if ($largeUnitId && $displayUnitId && $largeUnitId !== $displayUnitId) {
            $minStock = UnitConversionService::convertQuantity($product, $minStockBase, $largeUnitId, $displayUnitId) ?? $minStockBase;
        }

        $hasConversionChain = $largeUnitId && $smallUnitId && $largeUnitId !== $smallUnitId;

        return [
            'quantity' => $displayQty,
            'unit' => $displayUnit?->symbol ?? $displayUnit?->name ?? '-',
            'unit_id' => $displayUnitId,
            'min_stock' => $minStock,
            'stock_by_units' => $stockByUnits,
            'show_unit_detail' => count($stockByUnits) > 1 || $hasConversionChain,
            'conversion_hint' => $displayUnitId ? $product->getBarcodeUnitConversionHint($displayUnitId) : null,
        ];
    }

    /**
     * @param  array<int, array{unit_id: ?string, unit: string, quantity: float}>  $stockByUnits
     */
    protected function sumInUnit(Product $product, array $stockByUnits, ?string $targetUnitId): float
    {
        if (! $targetUnitId) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($stockByUnits as $row) {
            if (! $row['unit_id']) {
                continue;
            }

            if ($row['unit_id'] === $targetUnitId) {
                $total += $row['quantity'];

                continue;
            }

            $converted = UnitConversionService::convertQuantity(
                $product,
                $row['quantity'],
                $row['unit_id'],
                $targetUnitId
            );

            $total += $converted ?? 0.0;
        }

        return $total;
    }
}
