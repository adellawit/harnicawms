<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShippingRateSeeder extends Seeder
{
    /** Indonesian provinces present in cities.json / states.json */
    private const ID_PROVINCES = [
        'Aceh', 'Bali', 'Banten', 'Bengkulu', 'DI Yogyakarta', 'DKI Jakarta',
        'Gorontalo', 'Jambi', 'Jawa Barat', 'Jawa Tengah', 'Jawa Timur',
        'Kalimantan Barat', 'Kalimantan Selatan', 'Kalimantan Tengah', 'Kalimantan Timur', 'Kalimantan Utara',
        'Kepulauan Bangka Belitung', 'Kepulauan Riau', 'Lampung', 'Maluku', 'Maluku Utara',
        'Nusa Tenggara Barat', 'Nusa Tenggara Timur', 'Papua', 'Papua Barat', 'Papua Barat Daya',
        'Papua Pegunungan', 'Papua Selatan', 'Papua Tengah', 'Riau', 'Sulawesi Barat',
        'Sulawesi Selatan', 'Sulawesi Tengah', 'Sulawesi Tenggara', 'Sulawesi Utara',
        'Sumatera Barat', 'Sumatera Selatan', 'Sumatera Utara',
        // Alternate spellings sometimes present
        'Daerah Istimewa Yogyakarta', 'Jakarta',
    ];

    private const COURIERS = [
        ['code' => 'jne', 'service' => 'REG', 'name' => 'Reguler'],
        ['code' => 'jnt', 'service' => 'REG', 'name' => 'Reguler'],
        ['code' => 'sicepat', 'service' => 'REG', 'name' => 'Reguler'],
    ];

    /**
     * Province → zone key for pricing.
     */
    private const PROVINCE_ZONE = [
        'DKI Jakarta' => 'local',
        'Jakarta' => 'local',
        'Banten' => 'jabodetabek',
        'Jawa Barat' => 'jabodetabek',
        'Jawa Tengah' => 'jawa',
        'DI Yogyakarta' => 'jawa',
        'Daerah Istimewa Yogyakarta' => 'jawa',
        'Jawa Timur' => 'jawa',
        'Aceh' => 'sumatera',
        'Sumatera Utara' => 'sumatera',
        'Sumatera Barat' => 'sumatera',
        'Riau' => 'sumatera',
        'Kepulauan Riau' => 'sumatera',
        'Jambi' => 'sumatera',
        'Sumatera Selatan' => 'sumatera',
        'Bengkulu' => 'sumatera',
        'Lampung' => 'sumatera',
        'Kepulauan Bangka Belitung' => 'sumatera',
        'Kalimantan Barat' => 'kalimantan',
        'Kalimantan Tengah' => 'kalimantan',
        'Kalimantan Selatan' => 'kalimantan',
        'Kalimantan Timur' => 'kalimantan',
        'Kalimantan Utara' => 'kalimantan',
        'Sulawesi Utara' => 'sulawesi',
        'Sulawesi Tengah' => 'sulawesi',
        'Sulawesi Selatan' => 'sulawesi',
        'Sulawesi Tenggara' => 'sulawesi',
        'Gorontalo' => 'sulawesi',
        'Sulawesi Barat' => 'sulawesi',
        'Bali' => 'bali_nusa',
        'Nusa Tenggara Barat' => 'bali_nusa',
        'Nusa Tenggara Timur' => 'bali_nusa',
        'Maluku' => 'maluku_papua',
        'Maluku Utara' => 'maluku_papua',
        'Papua' => 'maluku_papua',
        'Papua Barat' => 'maluku_papua',
        'Papua Barat Daya' => 'maluku_papua',
        'Papua Pegunungan' => 'maluku_papua',
        'Papua Selatan' => 'maluku_papua',
        'Papua Tengah' => 'maluku_papua',
    ];

    private const ZONE_RATES = [
        'local' => ['base' => 10000, 'per_kg' => 2000, 'etd_min' => 1, 'etd_max' => 2],
        'jabodetabek' => ['base' => 14000, 'per_kg' => 2500, 'etd_min' => 1, 'etd_max' => 3],
        'jawa' => ['base' => 18000, 'per_kg' => 3000, 'etd_min' => 2, 'etd_max' => 4],
        'sumatera' => ['base' => 28000, 'per_kg' => 5000, 'etd_min' => 3, 'etd_max' => 6],
        'kalimantan' => ['base' => 38000, 'per_kg' => 7000, 'etd_min' => 4, 'etd_max' => 8],
        'sulawesi' => ['base' => 40000, 'per_kg' => 7500, 'etd_min' => 4, 'etd_max' => 8],
        'bali_nusa' => ['base' => 35000, 'per_kg' => 6500, 'etd_min' => 3, 'etd_max' => 7],
        'maluku_papua' => ['base' => 55000, 'per_kg' => 10000, 'etd_min' => 5, 'etd_max' => 12],
        'default' => ['base' => 45000, 'per_kg' => 8000, 'etd_min' => 4, 'etd_max' => 10],
    ];

    public function run(): void
    {
        if (ShippingRate::withTrashed()->exists()) {
            $this->command?->info('shipping_rates already has data, skipping...');

            return;
        }

        $origin = City::query()
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', ['jakarta pusat'])
            ->first();

        if (! $origin) {
            $origin = City::query()
                ->whereNull('deleted_at')
                ->where('name', 'ILIKE', '%Jakarta%')
                ->orderBy('name')
                ->first();
        }

        if (! $origin) {
            $this->command?->error('Origin city Jakarta not found. Seed cities first.');

            return;
        }

        $idProvinceIds = Province::query()
            ->whereNull('deleted_at')
            ->whereIn('name', self::ID_PROVINCES)
            ->pluck('id', 'name');

        if ($idProvinceIds->isEmpty()) {
            $this->command?->error('No Indonesian provinces found in public.provinces.');

            return;
        }

        $destinations = City::query()
            ->whereNull('deleted_at')
            ->whereIn('province_id', $idProvinceIds->values())
            ->where('id', '!=', $origin->id)
            ->get(['id', 'province_id']);

        if ($destinations->isEmpty()) {
            $this->command?->error('No destination cities found for Indonesia.');

            return;
        }

        $provinceNameById = $idProvinceIds->flip(); // id => name
        $now = Carbon::now();
        $rows = [];
        $count = 0;

        foreach ($destinations as $dest) {
            $provinceName = $provinceNameById[$dest->province_id] ?? null;
            $zoneKey = self::PROVINCE_ZONE[$provinceName] ?? 'default';
            $zone = self::ZONE_RATES[$zoneKey];

            foreach (self::COURIERS as $courier) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'origin_city_id' => $origin->id,
                    'destination_city_id' => $dest->id,
                    'courier_code' => $courier['code'],
                    'service_code' => $courier['service'],
                    'service_name' => $courier['name'],
                    'base_amount' => $zone['base'],
                    'per_kg_amount' => $zone['per_kg'],
                    'etd_min_days' => $zone['etd_min'],
                    'etd_max_days' => $zone['etd_max'],
                    'is_active' => true,
                    'notes' => 'seed:zone:'.$zoneKey,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $count++;

                if (count($rows) >= 500) {
                    DB::table('master_data.shipping_rates')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('master_data.shipping_rates')->insert($rows);
        }

        $this->command?->info("Seeded {$count} shipping rates from {$origin->name} to Indonesia cities.");
    }
}
