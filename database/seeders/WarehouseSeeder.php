<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Warehouse;
use App\Services\MasterData\WarehouseBootstrapService;
use Illuminate\Database\Seeder;

/**
 * Ensures every branch has warehouses in master_data.warehouses
 * and syncs legacy business_units (type_code = WAREHOUSE) into the new table.
 */
class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        app(WarehouseBootstrapService::class)->syncForSeeding();
        $this->ensureBandungWarehouses();

        $this->command?->info('Warehouses seeded/synced successfully.');
    }

    private function ensureBandungWarehouses(): void
    {
        $branch = BusinessUnit::query()
            ->where('type_code', 'BRANCH')
            ->where('code', 'SUHARA-BDG-001')
            ->whereNull('deleted_at')
            ->first(['id', 'parent_id']);

        if (! $branch) {
            $this->command?->warn('Branch Bandung tidak ditemukan. Jalankan BusinessUnitSeeder terlebih dahulu.');

            return;
        }

        $warehouses = [
            [
                'code' => 'SUHARA-BDG-WH-RM',
                'type' => 'RAW_MATERIAL',
                'name' => 'Gudang Raw Material',
                'short_name' => 'RM',
                'is_default' => true,
            ],
            [
                'code' => 'SUHARA-BDG-WH-PRD',
                'type' => 'FG',
                'name' => 'Gudang Product',
                'short_name' => 'PRD',
                'is_default' => false,
            ],
            [
                'code' => 'SUHARA-BDG-WH-DEF',
                'type' => 'QUARANTINE',
                'name' => 'Gudang Defect',
                'short_name' => 'DEF',
                'is_default' => false,
            ],
            [
                'code' => 'SUHARA-BDG-WH-MKT',
                'type' => 'MARKETING',
                'name' => 'Gudang Marketing',
                'short_name' => 'MKT',
                'is_default' => false,
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(
                [
                    'company_id' => $branch->parent_id,
                    'code' => $warehouse['code'],
                ],
                [
                    'branch_id' => $branch->id,
                    'warehouse_type_code' => $warehouse['type'],
                    'name' => $warehouse['name'],
                    'short_name' => $warehouse['short_name'],
                    'is_default' => $warehouse['is_default'],
                    'is_inventory_active' => true,
                    'is_active' => true,
                ]
            );
        }

        // Align existing "Gudang Marketing" (auto-coded WH-00001) to MARKETING type.
        Warehouse::query()
            ->where('branch_id', $branch->id)
            ->where(function ($q) {
                $q->where('code', 'WH-00001')
                    ->orWhere('name', 'Gudang Marketing');
            })
            ->update([
                'warehouse_type_code' => 'MARKETING',
                'is_inventory_active' => true,
                'is_active' => true,
            ]);

        Warehouse::query()
            ->where('branch_id', $branch->id)
            ->where('code', '!=', 'SUHARA-BDG-WH-RM')
            ->update(['is_default' => false]);

        Warehouse::query()
            ->where('branch_id', $branch->id)
            ->where('code', 'SUHARA-BDG-WH-RM')
            ->update(['is_default' => true]);
    }
}
