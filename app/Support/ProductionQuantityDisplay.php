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
        $product->loadMissing(['unitConversions', 'defaultUnit']);

        $smallestUnitId = $product->getSmallestUnitId();
        if (! $smallestUnitId) {
            return null;
        }

        $smallestQty = UnitConversionService::convertQuantity($product, $qty, $unitId, $smallestUnitId);
        if ($smallestQty === null || $smallestQty <= 0) {
            return null;
        }

        $breakdown = ProductionSimulationService::decomposeSmallestQuantity($product, $smallestQty);
        if ($breakdown === []) {
            return null;
        }

        // Sensor hanya untuk kasus benar-benar identik (mis. qty "1 krt" pecah jadi "1 krt" lagi
        // — tidak ada info baru). Selain itu SELALU ditampilkan, termasuk saat breakdown cuma
        // 1 unit (mis. "7 pack" dari qty pecahan krt) — justru itu yang paling dibutuhkan untuk
        // melihat hasil produksi partial batch yang sebenarnya.
        if (count($breakdown) === 1 && ($breakdown[0]['unit_id'] ?? null) === $unitId) {
            return null;
        }

        return implode(' · ', collect($breakdown)
            ->map(fn (array $row) => self::formatQty($row['qty']).' '.$row['label'])
            ->all());
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
