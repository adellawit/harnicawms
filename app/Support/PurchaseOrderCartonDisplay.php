<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Support\Collection;

/**
 * Hitung tampilan Carton (MC) dari qty PO + satuan + rantai konversi produk.
 */
class PurchaseOrderCartonDisplay
{
    public static function format(Product $product, float $quantity, string $unitId, ?Collection $unitsById = null): string
    {
        if ($quantity <= 0) {
            return '-';
        }

        $product->loadMissing(['unitConversions', 'defaultUnit']);
        $unitsById ??= ProductUnit::query()->get()->keyBy('id');

        $smallestId = $product->getSmallestUnitId();
        $defaultId = $product->default_unit_id;

        $qtyBox = $product->convertQuantity($quantity, $unitId, $smallestId);
        if ($qtyBox === null) {
            $unit = $unitsById->get($unitId);
            $name = $unit?->name ?? 'Unit';

            return self::formatNumber($quantity).' '.$name;
        }

        $boxName = $unitsById->get($smallestId)?->name ?? 'Box';

        if ($unitId !== $defaultId) {
            return self::formatNumber($qtyBox).' '.$boxName;
        }

        $chain = self::downstreamChain($product, $defaultId, $smallestId);
        if ($chain === []) {
            return self::formatNumber($qtyBox).' '.$boxName;
        }

        $parts = [];
        foreach ($chain as $stepUnitId) {
            $converted = $product->convertQuantity($quantity, $unitId, $stepUnitId);
            if ($converted === null) {
                continue;
            }
            $stepName = $unitsById->get($stepUnitId)?->name ?? 'Unit';
            $parts[] = self::formatNumber($converted).' '.$stepName;
        }

        return $parts !== [] ? implode(' ', $parts) : self::formatNumber($qtyBox).' '.$boxName;
    }

    public static function boxQuantity(Product $product, float $quantity, string $unitId): ?float
    {
        if ($quantity <= 0) {
            return null;
        }

        $product->loadMissing('unitConversions');
        $smallestId = $product->getSmallestUnitId();

        return $product->convertQuantity($quantity, $unitId, $smallestId);
    }

    /**
     * @return list<string>
     */
    protected static function downstreamChain(Product $product, string $defaultId, string $smallestId): array
    {
        $conversions = $product->unitConversions->sortBy('conversion_level');
        $chain = [];
        $current = $defaultId;

        while ($current !== $smallestId) {
            $conv = $conversions->first(fn ($c) => $c->from_unit_id === $current);
            if (! $conv) {
                break;
            }
            $chain[] = $conv->to_unit_id;
            $current = $conv->to_unit_id;
        }

        return $chain;
    }

    protected static function formatNumber(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 6, ',', '.'), '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    }
}
