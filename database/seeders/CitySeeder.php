<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonMachine\Items;

class CitySeeder extends Seeder
{
    // Temporary increase memory_limit
    public function __construct() {
        ini_set('memory_limit', -1);
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip if data already exists
        if (City::count() > 0) {
            $this->command->info('Cities table already has data, skipping...');
            return;
        }

        // Load province ID mapping
        $mappingPath = storage_path('app/province_id_mapping.json');
        if (!file_exists($mappingPath)) {
            $this->command->error('Province ID mapping not found! Run ProvinceSeeder first.');
            return;
        }
        $provinceMapping = json_decode(file_get_contents($mappingPath), true);

        $cities = Items::fromFile('database/data/cities.json');
        $data = [];
        $now = Carbon::now();

        foreach ($cities as $city) {
            // Map old state_id to new UUID
            $provinceId = $provinceMapping[$city->state_id] ?? null;

            if (!$provinceId) {
                $this->command->warn("Skipping city '{$city->name}' - province ID {$city->state_id} not found in mapping");
                continue;
            }

            $data[] = [
                "id" => Str::uuid()->toString(),
                "name" => $city->name,
                "province_id" => $provinceId,
                "latitude" => $city->latitude,
                "longitude" => $city->longitude,
                "created_at" => $now,
                "updated_at" => $now,
            ];
        }

        foreach (array_chunk($data, 500) as $chunk) {
            City::insert($chunk);
        }

        // Clean up mapping file
        if (file_exists($mappingPath)) {
            unlink($mappingPath);
        }
    }
}
