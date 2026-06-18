<?php

namespace Database\Seeders;

use App\Services\MasterData\WarehouseBootstrapService;
use Illuminate\Database\Seeder;

/**
 * Ensures every branch has at least one warehouse in master_data.warehouses
 * and syncs legacy business_units (type_code = WAREHOUSE) into the new table.
 */
class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        app(WarehouseBootstrapService::class)->syncForSeeding();

        $this->command?->info('Warehouses seeded/synced successfully.');
    }
}
