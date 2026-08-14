<?php

namespace App\Services\Shipping;

use App\Models\Partner\Agent;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Services\Shop\ShopCartService;

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

        $shipping = app(PosShippingOptionsService::class);
        $destCityId = $shipping->resolveCityId($agent->city_id, $agent->city);
        $branchId = $agent->customer?->getBranchId();
        $quote = $shipping->quote($branchId, $destCityId, $cart->get()['items'] ?? []);
        $amounts = collect($quote['options'] ?? [])
            ->filter(fn (array $opt) => ! empty($opt['rate_id']))
            ->pluck('amount')
            ->filter(fn ($amount) => (float) $amount > 0);

        return $amounts->isEmpty() ? null : (float) $amounts->min();
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
