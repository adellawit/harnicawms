<?php

namespace App\Services\Shipping;

use App\Models\City;
use App\Models\Province;
use App\Models\ShippingRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ShippingRateCsvImporter
{
    public const HEADERS = [
        'origin_city',
        'origin_province',
        'destination_city',
        'destination_province',
        'courier_code',
        'service_code',
        'service_name',
        'base_amount',
        'per_kg_amount',
        'etd_min_days',
        'etd_max_days',
        'is_active',
        'notes',
    ];

    /**
     * @return array{success:int,failed:int,errors:array<int,string>}
     */
    public function import(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['Cannot open CSV file.']];
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return ['success' => 0, 'failed' => 0, 'errors' => ['CSV is empty.']];
        }

        $header = array_map(fn ($h) => Str::of((string) $h)->trim()->lower()->toString(), $header);
        foreach (self::HEADERS as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);

                return ['success' => 0, 'failed' => 0, 'errors' => ["Missing column: {$required}"]];
            }
        }

        $success = 0;
        $failed = 0;
        $errors = [];
        $rowNum = 1;
        $userId = Auth::guard('web')->id();

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $data = [];
            foreach ($header as $i => $key) {
                $data[$key] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }

            try {
                $this->upsertRow($data, $userId);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Row {$rowNum}: ".$e->getMessage();
            }
        }

        fclose($handle);

        return compact('success', 'failed', 'errors');
    }

    public function templateCsv(): string
    {
        $lines = [implode(',', self::HEADERS)];
        $lines[] = 'Jakarta Pusat,DKI Jakarta,Bandung,Jawa Barat,jne,REG,Reguler,15000,3000,2,3,1,sample';

        return implode("\n", $lines)."\n";
    }

    private function upsertRow(array $data, ?string $userId): void
    {
        $courier = strtolower($data['courier_code'] ?? '');
        if (! array_key_exists($courier, ShippingRate::COURIERS)) {
            throw new \InvalidArgumentException("Invalid courier_code '{$courier}'.");
        }

        $serviceCode = strtoupper($data['service_code'] ?? '');
        if ($serviceCode === '') {
            throw new \InvalidArgumentException('service_code is required.');
        }

        $origin = $this->resolveCity($data['origin_city'] ?? '', $data['origin_province'] ?? '');
        $dest = $this->resolveCity($data['destination_city'] ?? '', $data['destination_province'] ?? '');

        if ($origin->id === $dest->id) {
            throw new \InvalidArgumentException('Origin and destination must differ.');
        }

        $base = $this->toDecimal($data['base_amount'] ?? '0', 'base_amount');
        $perKg = $this->toDecimal($data['per_kg_amount'] ?? '0', 'per_kg_amount');

        $payload = [
            'service_name' => $data['service_name'] !== '' ? $data['service_name'] : $serviceCode,
            'base_amount' => $base,
            'per_kg_amount' => $perKg,
            'etd_min_days' => $this->toNullableInt($data['etd_min_days'] ?? null),
            'etd_max_days' => $this->toNullableInt($data['etd_max_days'] ?? null),
            'is_active' => $this->toBool($data['is_active'] ?? '1'),
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
            'updated_by' => $userId,
        ];

        $existing = ShippingRate::withTrashed()
            ->where('origin_city_id', $origin->id)
            ->where('destination_city_id', $dest->id)
            ->where('courier_code', $courier)
            ->where('service_code', $serviceCode)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $payload['deleted_by'] = null;
            }
            $existing->update($payload);
        } else {
            ShippingRate::create(array_merge($payload, [
                'origin_city_id' => $origin->id,
                'destination_city_id' => $dest->id,
                'courier_code' => $courier,
                'service_code' => $serviceCode,
                'created_by' => $userId,
            ]));
        }
    }

    private function resolveCity(string $cityName, string $provinceName): City
    {
        if ($cityName === '' || $provinceName === '') {
            throw new \InvalidArgumentException('City and province names are required.');
        }

        $province = Province::query()
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($provinceName)])
            ->first();

        if (! $province) {
            throw new \InvalidArgumentException("Province '{$provinceName}' not found.");
        }

        $city = City::query()
            ->whereNull('deleted_at')
            ->where('province_id', $province->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($cityName)])
            ->first();

        if (! $city) {
            throw new \InvalidArgumentException("City '{$cityName}' not found in '{$provinceName}'.");
        }

        return $city;
    }

    private function toDecimal(string $value, string $field): float
    {
        if (! is_numeric($value) || (float) $value < 0) {
            throw new \InvalidArgumentException("{$field} must be a non-negative number.");
        }

        return round((float) $value, 2);
    }

    private function toNullableInt(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        if (! ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('ETD days must be integers.');
        }

        return (int) $value;
    }

    private function toBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y'], true);
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
