<?php

namespace App\Services\Partner;

final class ForediCoordNormalizer
{
    public const SCALE = 100000.0;

    /**
     * Normalize Excel Lat/Long (stored as ×100000 integers) into WGS84 degrees.
     *
     * @return array{lat: float, long: float}|null
     */
    public static function normalize(mixed $latRaw, mixed $longRaw): ?array
    {
        if ($latRaw === null || $latRaw === '' || $longRaw === null || $longRaw === '') {
            return null;
        }

        if (! is_numeric($latRaw) || ! is_numeric($longRaw)) {
            return null;
        }

        $lat = ((float) $latRaw) / self::SCALE;
        $long = ((float) $longRaw) / self::SCALE;

        if ($lat < -90.0 || $lat > 90.0 || $long < -180.0 || $long > 180.0) {
            return null;
        }

        return [
            'lat' => $lat,
            'long' => $long,
        ];
    }
}
