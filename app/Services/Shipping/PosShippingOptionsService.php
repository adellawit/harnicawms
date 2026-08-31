<?php

namespace App\Services\Shipping;

use App\Models\City;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Models\Warehouse;
use App\Support\WmsContext;

class PosShippingOptionsService
{
    public function __construct(
        protected AgentShippingEstimator $etdFormatter,
    ) {}

    /**
     * @param  array<int, array{variant_id?: string, quantity?: mixed}>  $items
     * @return array{
     *   origin_city_id: ?string,
     *   origin_city_name: ?string,
     *   weight_kg: float,
     *   hint: ?string,
     *   options: list<array<string, mixed>>
     * }
     */
    public function quote(
        ?string $branchId,
        ?string $destinationCityId,
        array $items,
        ?Warehouse $warehouse = null,
    ): array
    {
        $warehouse = $warehouse ?: WmsContext::salesSourceWarehouse($branchId);
        $origin = $this->resolveOriginCity($warehouse);
        $weightKg = $this->cartWeightKg($items);

        $payload = [
            'origin_city_id' => $origin['id'],
            'origin_city_name' => $origin['name'],
            'weight_kg' => $weightKg,
            'hint' => null,
            'options' => [],
        ];

        if (! $origin['id']) {
            $payload['hint'] = 'Kota gudang belum di-set. Ongkir bisa diisi manual.';
            if ($destinationCityId) {
                $fallback = $this->ratesToDestination($destinationCityId);
                $payload['options'] = $fallback->isNotEmpty()
                    ? $this->mapRateOptions($fallback, $weightKg)
                    : $this->manualCourierOptions();
            }

            return $payload;
        }

        if (! $destinationCityId) {
            return $payload;
        }

        $rates = $this->ratesForRoute($origin['id'], $destinationCityId);
        $usedFallback = false;

        if ($rates->isEmpty()) {
            $rates = $this->ratesToDestination($destinationCityId);
            $usedFallback = $rates->isNotEmpty();
        }

        if ($rates->isEmpty()) {
            $destName = City::query()->where('id', $destinationCityId)->value('name');
            $from = $origin['name'] ?: 'asal';
            $to = $destName ?: 'tujuan';
            $payload['hint'] = "Tidak ada tarif {$from} → {$to}. Pilih kurir, isi ongkir manual.";
            $payload['options'] = $this->manualCourierOptions();

            return $payload;
        }

        $payload['options'] = $this->mapRateOptions($rates, $weightKg);

        if ($usedFallback) {
            $payload['hint'] = 'Tarif rute gudang tidak ada. Dipakai tarif master ke kota tujuan.';
        }

        return $payload;
    }

    public function assertUsableRate(ShippingRate $rate, ?string $branchId, ?string $destinationCityId): void
    {
        if ($destinationCityId && $rate->destination_city_id !== $destinationCityId) {
            throw new \InvalidArgumentException('Tarif ongkir tidak sesuai kota tujuan.');
        }

        $origin = $this->resolveOriginCity(WmsContext::salesSourceWarehouse($branchId));
        if (! $origin['id'] || $rate->origin_city_id === $origin['id']) {
            return;
        }

        if ($destinationCityId && $this->ratesForRoute($origin['id'], $destinationCityId)->isEmpty()) {
            $fallbackIds = $this->ratesToDestination($destinationCityId)->pluck('id');
            if ($fallbackIds->contains($rate->id)) {
                return;
            }
        }

        throw new \InvalidArgumentException('Tarif ongkir tidak sesuai kota gudang.');
    }

    /**
     * @param  array<int, array{variant_id?: string, quantity?: mixed}>  $items
     */
    public function cartWeightKg(array $items): float
    {
        if ($items === []) {
            return 0;
        }

        $variantIds = array_values(array_filter(array_column($items, 'variant_id')));
        if ($variantIds === []) {
            return 0;
        }

        $weights = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->pluck('weight', 'id');

        $total = 0.0;
        foreach ($items as $item) {
            $variantId = $item['variant_id'] ?? null;
            if (! $variantId) {
                continue;
            }
            $weight = (float) ($weights[$variantId] ?? 0);
            $total += $weight * (float) ($item['quantity'] ?? 0);
        }

        return max(0, $total);
    }

    /**
     * @return array{id: ?string, name: ?string}
     */
    public function resolveOriginCity(?Warehouse $warehouse): array
    {
        if (! $warehouse) {
            return ['id' => null, 'name' => null];
        }

        $warehouse->loadMissing('cityRef');
        $id = $warehouse->city_id;
        $name = $warehouse->cityRef?->name ?: $warehouse->city;

        if (! $id && $warehouse->city) {
            $id = City::query()
                ->whereNull('deleted_at')
                ->where('name', 'ILIKE', trim((string) $warehouse->city))
                ->value('id');
        }

        return ['id' => $id, 'name' => $name];
    }

    public function resolveCityId(?string $cityId, ?string $cityName): ?string
    {
        if ($cityId) {
            return $cityId;
        }

        $cityName = trim((string) $cityName);
        if ($cityName === '') {
            return null;
        }

        return City::query()
            ->whereNull('deleted_at')
            ->where('name', 'ILIKE', $cityName)
            ->value('id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, ShippingRate>
     */
    protected function ratesForRoute(string $originCityId, string $destinationCityId)
    {
        return ShippingRate::query()
            ->where('origin_city_id', $originCityId)
            ->where('destination_city_id', $destinationCityId)
            ->where('is_active', true)
            ->orderBy('courier_code')
            ->orderBy('service_code')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ShippingRate>
     */
    protected function ratesToDestination(string $destinationCityId)
    {
        return ShippingRate::query()
            ->where('destination_city_id', $destinationCityId)
            ->where('is_active', true)
            ->orderBy('courier_code')
            ->orderBy('service_code')
            ->orderBy('base_amount')
            ->get()
            ->unique(fn (ShippingRate $rate) => $rate->courier_code.'|'.$rate->service_code)
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ShippingRate>  $rates
     * @return list<array<string, mixed>>
     */
    protected function mapRateOptions($rates, float $weightKg): array
    {
        return $rates->map(function (ShippingRate $rate) use ($weightKg) {
            $amount = $rate->estimateForWeightKg($weightKg);
            $etd = $this->etdFormatter->formatShippingEtd($rate);
            $courier = ShippingRate::COURIERS[$rate->courier_code] ?? strtoupper((string) $rate->courier_code);
            $service = $rate->service_name ?: $rate->service_code;

            return [
                'rate_id' => $rate->id,
                'courier_code' => $rate->courier_code,
                'courier_label' => $courier,
                'service_code' => $rate->service_code,
                'service_name' => $rate->service_name,
                'amount' => $amount,
                'etd' => $etd,
                'manual' => false,
                'label' => trim($courier.' '.$service)
                    .' · Rp '.number_format($amount, 0, ',', '.')
                    .($etd ? ' · '.$etd : ''),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function manualCourierOptions(): array
    {
        $options = [];
        foreach (ShippingRate::COURIERS as $code => $label) {
            $options[] = [
                'rate_id' => null,
                'value' => 'manual:'.$code,
                'courier_code' => $code,
                'service_code' => null,
                'service_name' => null,
                'amount' => 0,
                'etd' => null,
                'manual' => true,
                'label' => $label,
            ];
        }

        return $options;
    }
}
