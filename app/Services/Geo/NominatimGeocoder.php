<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NominatimGeocoder
{
    private float $lastRequestAt = 0;

    /**
     * @return array{lat: float, long: float}|null
     */
    public function geocode(string $address, ?string $city = null): ?array
    {
        $address = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $address) ?? $address);
        $city = $city ? trim($city) : null;

        $candidates = [];
        if ($address !== '') {
            $candidates[] = $city ? "{$address}, {$city}, Indonesia" : "{$address}, Indonesia";
            // Shorter street-focused query (drop RT/RW noise)
            $short = preg_replace('/\b(RT|RW|Rt|Rw)[.\s\/0-9]+/u', '', $address) ?? $address;
            $short = trim(preg_replace('/\s+/', ' ', $short) ?? $short);
            if ($short !== '' && strcasecmp($short, $address) !== 0) {
                $candidates[] = $city ? "{$short}, {$city}, Indonesia" : "{$short}, Indonesia";
            }
        }
        if ($city) {
            $candidates[] = "{$city}, Indonesia";
        }

        foreach (array_unique($candidates) as $query) {
            $result = $this->search($query);
            if ($result) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return array{lat: float, long: float}|null
     */
    private function search(string $query): ?array
    {
        $this->throttle();

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'WMS-Harnica-PartnerImport/1.0 (local-dev)',
                    'Accept' => 'application/json',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'id',
                ]);

            if (! $response->successful()) {
                Log::warning('Nominatim geocode HTTP error', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return null;
            }

            $row = $response->json()[0] ?? null;
            if (! $row || ! isset($row['lat'], $row['lon'])) {
                return null;
            }

            return [
                'lat' => (float) $row['lat'],
                'long' => (float) $row['lon'],
            ];
        } catch (\Throwable $e) {
            Log::warning('Nominatim geocode failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function throttle(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRequestAt;
        if ($this->lastRequestAt > 0 && $elapsed < 1.05) {
            usleep((int) ((1.05 - $elapsed) * 1_000_000));
        }
        $this->lastRequestAt = microtime(true);
    }
}
