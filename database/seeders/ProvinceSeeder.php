<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonMachine\Items;

class ProvinceSeeder extends Seeder
{
    // Temporary increase memory_limit
    public function __construct()
    {
        ini_set('memory_limit', -1);
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip if data already exists
        if (Province::count() > 0) {
            $this->command->info('Provinces table already has data, skipping...');
            return;
        }

        $states = Items::fromFile('database/data/states.json');
        $data = [];
        $now = Carbon::now();
        $mapping = [];
        $usedCodes = [];

        foreach ($states as $state) {
            // Generate UUID for this province
            $uuid = Str::uuid()->toString();

            // Store mapping for CitySeeder to use
            $mapping[$state->id] = $uuid;

            // Handle code - ensure uniqueness
            $originalCode = $state->state_code;
            $code = $originalCode;

            // If no state_code, generate from name
            if ($code === null || $code === '') {
                $baseCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $state->name), 0, 3));
                if (strlen($baseCode) < 2) {
                    $baseCode = strtoupper(substr($state->name, 0, 2));
                }
                $code = $baseCode;
            }

            // Ensure uniqueness (for both generated and original codes)
            $finalCode = $code;
            $counter = 1;
            while (isset($usedCodes[$finalCode])) {
                $finalCode = $code . $counter;
                $counter++;
            }

            $code = $finalCode;
            $usedCodes[$code] = true;

            $data[] = [
                "id" => $uuid,
                "name" => $state->name,
                "code" => $code,
                "latitude" => $state->latitude,
                "longitude" => $state->longitude,
                "created_at" => $now,
                "updated_at" => $now,
            ];
        }

        foreach (array_chunk($data, 500) as $chunk) {
            Province::insert($chunk);
        }

        // Save mapping to a temporary file for CitySeeder
        file_put_contents(
            storage_path('app/province_id_mapping.json'),
            json_encode($mapping, JSON_PRETTY_PRINT)
        );

        $this->command->info('Provinces seeded successfully: ' . count($data) . ' records.');
    }
}
