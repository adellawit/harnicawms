<?php

namespace App\Services\Manufacturing;

use App\Models\BillOfMaterial;
use App\Models\Product;
use App\Services\StockAvailabilityService;
use App\Services\UnitConversionService;

/**
 * Simulasi produksi: kebutuhan bahan, hasil FG, dan sisa stok per level kemasan.
 */
class ProductionSimulationService
{
    /**
     * @return array<string, mixed>
     */
    public static function simulate(
        BillOfMaterial $bom,
        string $branchId,
        string $warehouseId,
        float $plannedQty,
        string $productionUnitId
    ): array {
        $bom->loadMissing([
            'product.unitConversions',
            'product.defaultUnit',
            'outputUnit',
            'variant',
            'items.componentVariant.product.unitConversions',
            'items.componentVariant.product.defaultUnit',
            'items.unit',
        ]);

        $outputProduct = $bom->product;
        $outputUnitId = $bom->output_unit_id;
        $plannedInOutputUnit = UnitConversionService::convertQuantity(
            $outputProduct,
            $plannedQty,
            $productionUnitId,
            $outputUnitId
        );

        if ($plannedInOutputUnit === null) {
            throw new \InvalidArgumentException('Satuan produksi tidak dapat dikonversi ke satuan output BOM.');
        }

        $scale = (float) $bom->output_quantity > 0
            ? ($plannedInOutputUnit / (float) $bom->output_quantity)
            : $plannedInOutputUnit;

        $outputUnits = self::unitOptions($outputProduct);
        $productionUnit = collect($outputUnits)->firstWhere('id', $productionUnitId);

        $materials = $bom->items->map(function ($item) use ($branchId, $warehouseId, $scale) {
            $componentProduct = $item->componentVariant?->product;
            $qtyNeeded = (float) $item->quantity * $scale;

            $available = StockAvailabilityService::availableQuantity(
                $item->component_variant_id,
                $branchId,
                $item->unit_id,
                $warehouseId
            );

            $smallestUnitId = $componentProduct?->getSmallestUnitId() ?: $item->unit_id;
            $availableSmallest = $componentProduct
                ? (UnitConversionService::convertQuantity($componentProduct, $available, $item->unit_id, $smallestUnitId) ?? $available)
                : $available;
            $neededSmallest = $componentProduct
                ? (UnitConversionService::convertQuantity($componentProduct, $qtyNeeded, $item->unit_id, $smallestUnitId) ?? $qtyNeeded)
                : $qtyNeeded;
            $afterSmallest = max(0, $availableSmallest - $neededSmallest);

            return [
                'label' => $item->componentVariant?->display_name ?? $item->componentProduct?->name ?? '-',
                'unit' => $item->unit?->symbol ?? $item->unit?->name ?? '',
                'unit_id' => $item->unit_id,
                'available' => $available,
                'needed' => $qtyNeeded,
                'after' => max(0, $available - $qtyNeeded),
                'sufficient' => $available + 1e-6 >= $qtyNeeded,
                'smallest_unit_label' => self::unitLabel($componentProduct, $smallestUnitId),
                'available_smallest' => round($availableSmallest, 4),
                'needed_smallest' => round($neededSmallest, 4),
                'after_smallest' => round($afterSmallest, 4),
                'stock_before_breakdown' => $componentProduct
                    ? self::decomposeSmallestQuantity($componentProduct, $availableSmallest)
                    : [],
                'stock_after_breakdown' => $componentProduct
                    ? self::decomposeSmallestQuantity($componentProduct, $afterSmallest)
                    : [],
                'remainder_breakdown' => $componentProduct
                    ? self::decomposeSmallestQuantity($componentProduct, $afterSmallest)
                    : [],
            ];
        })->values()->all();

        $outputSmallestId = $outputProduct->getSmallestUnitId() ?: $outputUnitId;
        $outputSmallestQty = UnitConversionService::convertQuantity(
            $outputProduct,
            $plannedInOutputUnit,
            $outputUnitId,
            $outputSmallestId
        ) ?? $plannedInOutputUnit;

        $allSufficient = collect($materials)->every(fn (array $row) => $row['sufficient']);

        return [
            'output_product' => $bom->variant?->display_name ?? $outputProduct->name,
            'output_unit' => $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? '',
            'output_unit_id' => $outputUnitId,
            'production_unit_id' => $productionUnitId,
            'production_unit' => $productionUnit['label'] ?? '',
            'planned_qty' => $plannedQty,
            'planned_qty_in_output_unit' => round($plannedInOutputUnit, 6),
            'output_units' => $outputUnits,
            'output_breakdown' => self::decomposeSmallestQuantity($outputProduct, $outputSmallestQty),
            'materials' => $materials,
            'all_sufficient' => $allSufficient,
            'conversion_chain' => self::buildUnitChain($outputProduct)->map(fn (array $row) => [
                'unit_id' => $row['unit']->id,
                'label' => $row['unit']->symbol ?: $row['unit']->name,
                'factor_to_next' => $row['factor_to_next'],
                'hint' => $outputProduct->getBarcodeUnitConversionHint($row['unit']->id),
            ])->values()->all(),
        ];
    }

    /**
     * Rantai satuan dari default unit ke satuan terkecil (tanpa query tambahan bila relasi sudah dimuat).
     *
     * @return \Illuminate\Support\Collection<int, array{unit: \App\Models\ProductUnit, level: int, factor_to_next: float|null}>
     */
    public static function buildUnitChain(Product $product): \Illuminate\Support\Collection
    {
        if (! $product->relationLoaded('unitConversions')) {
            $product->loadMissing(['unitConversions.fromUnit', 'unitConversions.toUnit']);
        } elseif ($product->unitConversions instanceof \Illuminate\Database\Eloquent\Collection) {
            $product->unitConversions->loadMissing(['fromUnit', 'toUnit']);
        }

        if (! $product->relationLoaded('defaultUnit')) {
            $product->loadMissing('defaultUnit');
        }

        $chain = collect();

        if (! $product->default_unit_id || ! $product->defaultUnit) {
            return $chain;
        }

        $items = [[
            'unit' => $product->defaultUnit,
            'level' => 1,
            'factor_to_next' => null,
        ]];

        $currentUnitId = $product->default_unit_id;
        $visited = [];
        $level = 1;

        while (true) {
            $conv = $product->unitConversions
                ->sortBy('conversion_level')
                ->first(function ($c) use ($currentUnitId, $visited) {
                    $key = $c->id ?: ($c->from_unit_id.'|'.$c->to_unit_id);

                    return $c->from_unit_id === $currentUnitId && ! isset($visited[$key]);
                });

            if (! $conv?->toUnit) {
                break;
            }

            $visited[$conv->id ?: ($conv->from_unit_id.'|'.$conv->to_unit_id)] = true;
            $items[count($items) - 1]['factor_to_next'] = (float) $conv->conversion_factor;
            $level++;
            $items[] = [
                'unit' => $conv->toUnit,
                'level' => $level,
                'factor_to_next' => null,
            ];
            $currentUnitId = $conv->to_unit_id;
        }

        return collect($items);
    }

    /**
     * Pecah qty satuan terkecil menjadi kombinasi Karton + Pack + Box + ... + sisa.
     *
     * @return array<int, array{unit_id: string, label: string, qty: float}>
     */
    public static function decomposeSmallestQuantity(Product $product, float $smallestQty): array
    {
        if ($smallestQty <= 0) {
            return [];
        }

        if (! $product->relationLoaded('unitConversions')) {
            $product->loadMissing('unitConversions');
        }

        $levels = self::buildUnitChain($product)->values()->all();
        if ($levels === []) {
            return [];
        }

        $unitIds = array_map(fn (array $row) => $row['unit']->id, $levels);
        $factors = [];

        foreach ($unitIds as $index => $unitId) {
            $factor = 1.0;
            for ($j = $index; $j < count($unitIds) - 1; $j++) {
                $factor *= (float) ($levels[$j]['factor_to_next'] ?? 1);
            }
            $factors[$unitId] = $factor;
        }

        $remaining = round($smallestQty, 6);
        $breakdown = [];
        $lastIndex = count($levels) - 1;

        foreach ($levels as $index => $item) {
            $unitId = $item['unit']->id;
            $label = $item['unit']->symbol ?: $item['unit']->name;
            $factor = $factors[$unitId] ?? 1.0;

            if ($index === $lastIndex) {
                if ($remaining > 1e-6) {
                    $breakdown[] = [
                        'unit_id' => $unitId,
                        'label' => $label,
                        'qty' => round($remaining, 4),
                    ];
                }
                break;
            }

            if ($factor <= 0) {
                continue;
            }

            $full = (int) floor(($remaining / $factor) + 1e-9);
            if ($full > 0) {
                $breakdown[] = [
                    'unit_id' => $unitId,
                    'label' => $label,
                    'qty' => (float) $full,
                ];
                $remaining -= $full * $factor;
            }
        }

        return $breakdown;
    }

    /**
     * @return array<int, array{id: string, label: string, level: int}>
     */
    public static function unitOptions(Product $product): array
    {
        if (! $product->relationLoaded('unitConversions')) {
            $product->loadMissing('unitConversions');
        }

        return self::buildUnitChain($product)
            ->map(fn (array $row) => [
                'id' => $row['unit']->id,
                'label' => $row['unit']->symbol ?: $row['unit']->name,
                'level' => $row['level'],
                'hint' => $product->getBarcodeUnitConversionHint($row['unit']->id),
            ])
            ->values()
            ->all();
    }

    protected static function unitLabel(?Product $product, string $unitId): string
    {
        if (! $product) {
            return '';
        }

        $row = self::buildUnitChain($product)->first(fn (array $chainRow) => $chainRow['unit']->id === $unitId);
        $unit = $row['unit'] ?? null;

        return $unit?->symbol ?: $unit?->name ?: '';
    }
}
