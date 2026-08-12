<?php

namespace App\Services\Shipping;

use App\Models\Partner\Agent;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Services\Shop\ShopCartService;
use App\Support\WmsContext;

class AgentShippingEstimator
{
    public function cartTotalWeightKg(ShopCartService $cart): float
    {
        $items = $cart->get()['items'] ?? [];
        if ($items === []) {
            return 0;
        }

        $variantIds = array_column($items, 'variant_id');
        $weights = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->pluck('weight', 'id');

        $total = 0.0;
        foreach ($items as $item) {
            $weight = (float) ($weights[$item['variant_id']] ?? 0);
            $total += $weight * (float) $item['quantity'];
        }

        return max(0, $total);
    }

    public function lowestEstimate(ShopCartService $cart, ?Agent $agent): ?float
    {
        if (! $agent) {
            return null;
        }

        $originCityId = optional(WmsContext::finishedGoodsWarehouse())->city_id;
        $destCityId = $agent->city_id;

        if (! $originCityId || ! $destCityId) {
            return null;
        }

        $weightKg = $this->cartTotalWeightKg($cart);
        $rates = ShippingRate::query()
            ->where('origin_city_id', $originCityId)
            ->where('destination_city_id', $destCityId)
            ->where('is_active', true)
            ->get();

        if ($rates->isEmpty()) {
            return null;
        }

        return (float) $rates
            ->map(fn (ShippingRate $rate) => $rate->estimateForWeightKg($weightKg))
            ->min();
    }

    public function formatShippingEtd(ShippingRate $rate): ?string
    {
        $min = $rate->etd_min_days;
        $max = $rate->etd_max_days;

        if (! $min && ! $max) {
            return null;
        }

        $range = ($min && $max) ? "{$min}-{$max}" : (string) ($min ?: $max);

        return $range.' hari';
    }
}
