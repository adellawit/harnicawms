<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Services\Manufacturing\ProductionSimulationService;
use App\Services\UnitConversionService;
use App\Support\ProductionQuantityDisplay;
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
     *   stock_by_units: array<int, array{unit_id: ?string, unit: string, quantity: float, smallest_quantity?: float|null, smallest_unit?: string}>,
     *   show_unit_detail: bool,
     *   conversion_hint: ?string,
     *   conversion_chain_hint: ?string,
     *   packaging_breakdown: array<int, array{unit_id: string, label: string, qty: float}>,
     *   packaging_hint: ?string,
     *   smallest_quantity: float,
     *   smallest_unit: string,
     *   smallest_unit_id: ?string,
     *   has_smallest_display: bool
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
        $smallestUnit = $units->firstWhere('id', $smallUnitId) ?? null;
        $smallestUnitLabel = $smallestUnit?->symbol ?? $smallestUnit?->name ?? '-';

        if ($hasConversionChain && $smallUnitId) {
            $stockByUnits = array_map(function (array $row) use ($product, $smallUnitId, $smallestUnitLabel) {
                $smallestQty = $row['unit_id'] === $smallUnitId
                    ? $row['quantity']
                    : UnitConversionService::convertQuantity($product, $row['quantity'], $row['unit_id'], $smallUnitId);

                $row['smallest_quantity'] = $smallestQty;
                $row['smallest_unit'] = $smallestUnitLabel;

                return $row;
            }, $stockByUnits);
        }

        $packagingBreakdown = ($smallUnitId && $totalSmallest > 0)
            ? ProductionQuantityDisplay::packagingBreakdown($product, $totalSmallest, $smallUnitId)
            : [];

        $packagingHint = $packagingBreakdown === []
            ? null
            : implode(' · ', array_map(
                fn (array $row) => $this->formatQtyLabel((float) $row['qty']).' '.$row['label'],
                $packagingBreakdown
            ));

        // Detail per unit stock hanya relevan bila stok tersimpan di >1 satuan fisik.
        $showUnitDetail = count($stockByUnits) > 1;

        return [
            'quantity' => $displayQty,
            'unit' => $displayUnit?->symbol ?? $displayUnit?->name ?? '-',
            'unit_id' => $displayUnitId,
            'min_stock' => $minStock,
            'stock_by_units' => $stockByUnits,
            'show_unit_detail' => $showUnitDetail,
            'conversion_hint' => $displayUnitId ? $product->getBarcodeUnitConversionHint($displayUnitId) : null,
            'conversion_chain_hint' => $this->buildConversionChainHint($product),
            'packaging_breakdown' => $packagingBreakdown,
            'packaging_hint' => $packagingHint,
            'smallest_quantity' => $totalSmallest,
            'smallest_unit' => $smallestUnitLabel,
            'smallest_unit_id' => $smallUnitId,
            'has_smallest_display' => $hasConversionChain,
        ];
    }

    protected function buildConversionChainHint(Product $product): ?string
    {
        $chain = ProductionSimulationService::buildUnitChain($product)->values();
        if ($chain->count() < 2) {
            return null;
        }

        $parts = [];
        $factorFromLargest = 1.0;
        $largestLabel = $chain[0]['unit']->symbol ?: $chain[0]['unit']->name;

        $parts[] = '1 '.$largestLabel;

        for ($i = 0; $i < $chain->count() - 1; $i++) {
            $factorToNext = (float) ($chain[$i]['factor_to_next'] ?? 1);
            if ($factorToNext <= 0) {
                continue;
            }
            $factorFromLargest *= $factorToNext;
            $label = $chain[$i + 1]['unit']->symbol ?: $chain[$i + 1]['unit']->name;
            $parts[] = $this->formatQtyLabel($factorFromLargest).' '.$label;
        }

        return count($parts) > 1 ? implode(' = ', $parts) : null;
    }

    protected function formatQtyLabel(float $qty): string
    {
        if (abs($qty - round($qty)) < 1e-9) {
            return (string) (int) round($qty);
        }

        return rtrim(rtrim(number_format($qty, 4, ',', '.'), '0'), ',');
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
