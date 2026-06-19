<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Warehouse;
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
        $this->ensureCompanyWarehouses();

        $this->command?->info('Warehouses seeded/synced successfully.');
    }

    private function ensureCompanyWarehouses(): void
    {
        $companies = BusinessUnit::query()
            ->where('type_code', 'COMPANY')
            ->whereNull('deleted_at')
            ->get(['id', 'code', 'name']);

        foreach ($companies as $company) {
            foreach ($this->companyWarehouseDefaults($company) as $warehouse) {
                Warehouse::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $warehouse['code'],
                    ],
                    [
                        'branch_id' => null,
                        'warehouse_type_code' => $warehouse['type'],
                        'name' => $warehouse['name'],
                        'short_name' => $warehouse['short_name'],
                        'is_default' => false,
                        'is_inventory_active' => true,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function companyWarehouseDefaults(BusinessUnit $company): array
    {
        $prefix = $company->code ?: 'COMPANY';

        return [
            [
                'code' => "{$prefix}-WH-WIP",
                'type' => 'WIP',
                'name' => 'Gudang WIP (Bahan Baku & Proses)',
                'short_name' => 'WIP',
            ],
            [
                'code' => "{$prefix}-WH-FG",
                'type' => 'FG',
                'name' => 'Gudang Barang Jadi',
                'short_name' => 'FG',
            ],
        ];
    }
}
