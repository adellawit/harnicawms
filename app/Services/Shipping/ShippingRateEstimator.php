<?php

namespace App\Services\Shipping;

use App\Models\ShippingRate;

class ShippingRateEstimator
{
    /**
     * Estimate shipping cost from master rates.
     * Formula: base_amount + ceil(kg) * per_kg_amount (min 1 kg).
     */
    public function estimate(
        string $originCityId,
        string $destinationCityId,
        string $courierCode,
        string $serviceCode,
        float $weightKg = 1.0
    ): ?array {
        $rate = ShippingRate::query()
            ->where('origin_city_id', $originCityId)
            ->where('destination_city_id', $destinationCityId)
            ->where('courier_code', strtolower($courierCode))
            ->where('service_code', strtoupper($serviceCode))
            ->where('is_active', true)
            ->first();

        if (! $rate) {
            return null;
        }

        return [
            'rate_id' => $rate->id,
            'courier_code' => $rate->courier_code,
            'service_code' => $rate->service_code,
            'service_name' => $rate->service_name,
            'base_amount' => (float) $rate->base_amount,
            'per_kg_amount' => (float) $rate->per_kg_amount,
            'weight_kg' => max(1, (int) ceil($weightKg)),
            'amount' => $rate->estimateForWeightKg($weightKg),
            'etd_min_days' => $rate->etd_min_days,
            'etd_max_days' => $rate->etd_max_days,
        ];
    }
}
