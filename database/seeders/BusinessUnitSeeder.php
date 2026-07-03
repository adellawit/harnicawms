<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('TRUNCATE TABLE master_data.business_units CASCADE');

        $holding = BusinessUnit::create([
            'id' => '550e8f0e-1234-4c89-1234-567890abcdef',
            'parent_id' => null,
            'type_code' => 'HOLDING',
            'code' => 'HARNICA-HLD-001',
            'name' => 'Harnica',
            'brand_name' => 'Harnica',
            'email' => 'info@harnica.co.id',
            'phone' => '+62-22-12345678',
            'address' => 'Jl. Asia Afrika No. 20, Bandung',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40111',
            'country' => 'Indonesia',
            'is_pos_active' => false,
            'is_inventory_active' => false,
            'tax_type' => 'PPN',
            'tax_percentage' => 11.00,
            'service_charge_percentage' => 0,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $company = BusinessUnit::create([
            'id' => '7712a0a2-3456-6eab-3456-789012cdef12',
            'parent_id' => $holding->id,
            'type_code' => 'COMPANY',
            'code' => 'SUHARA-001',
            'name' => 'Suhara Botanica',
            'brand_name' => 'Suhara Botanica',
            'email' => 'info@suharabotanica.co.id',
            'phone' => '+62-22-87654321',
            'address' => 'Jl. Asia Afrika No. 20, Bandung',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40111',
            'country' => 'Indonesia',
            'npwp' => '01.234.567.8-901.000',
            'nib' => '1234567890123456',
            'is_pos_active' => false,
            'is_inventory_active' => false,
            'tax_type' => 'PPN',
            'tax_percentage' => 11.00,
            'service_charge_percentage' => 0,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $branch = BusinessUnit::create([
            'id' => '8823b1b3-4567-7fbc-4567-890123def123',
            'parent_id' => $company->id,
            'type_code' => 'BRANCH',
            'code' => 'SUHARA-BDG-001',
            'name' => 'Bandung',
            'brand_name' => 'Suhara Botanica Bandung',
            'email' => 'bandung@suharabotanica.co.id',
            'phone' => '+62-22-44556677',
            'address' => 'Jl. Asia Afrika No. 20, Bandung',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40111',
            'country' => 'Indonesia',
            'is_pos_active' => true,
            'is_inventory_active' => true,
            'tax_type' => 'PPN',
            'tax_percentage' => 11.00,
            'service_charge_percentage' => 0,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'opening_date' => '2020-06-01',
            'is_active' => true,
        ]);

        $warehouses = [
            [
                'id' => 'aa01c3d4-1111-4aaa-1111-aaaaaaaaaaa1',
                'code' => 'SUHARA-BDG-WH-RM',
                'name' => 'Gudang Raw Material',
                'brand_name' => 'RAW_MATERIAL',
            ],
            [
                'id' => 'aa01c3d4-1111-4aaa-1111-aaaaaaaaaaa2',
                'code' => 'SUHARA-BDG-WH-PRD',
                'name' => 'Gudang Product',
                'brand_name' => 'FG',
            ],
            [
                'id' => 'aa01c3d4-1111-4aaa-1111-aaaaaaaaaaa3',
                'code' => 'SUHARA-BDG-WH-DEF',
                'name' => 'Gudang Defect',
                'brand_name' => 'QUARANTINE',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            BusinessUnit::create([
                'id' => $warehouse['id'],
                'parent_id' => $branch->id,
                'type_code' => 'WAREHOUSE',
                'code' => $warehouse['code'],
                'name' => $warehouse['name'],
                'brand_name' => $warehouse['brand_name'],
                'email' => 'warehouse@suharabotanica.co.id',
                'phone' => '+62-22-44556677',
                'address' => 'Jl. Asia Afrika No. 20, Bandung',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'postal_code' => '40111',
                'country' => 'Indonesia',
                'is_pos_active' => false,
                'is_inventory_active' => true,
                'tax_type' => 'PPN',
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 0,
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'is_active' => true,
            ]);
        }

        $this->command->info('Business Units seeded successfully!');
        $this->command->info('  - Holding: Harnica (' . $holding->id . ')');
        $this->command->info('  - Company: Suhara Botanica (' . $company->id . ')');
        $this->command->info('  - Branch: Bandung (' . $branch->id . ')');
        $this->command->info('  - Warehouses: Gudang Raw Material, Gudang Product, Gudang Defect');
    }
}
