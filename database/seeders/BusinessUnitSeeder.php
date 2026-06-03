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
            'code' => 'WIT-HLD-001',
            'name' => 'WIT. Indonesia',
            'brand_name' => 'WIT. Indonesia',
            'email' => 'holding@wit.co.id',
            'phone' => '+62-21-12345678',
            'address' => 'Jl. Sudirman No. 1, Jakarta Pusat',
            'city' => 'Jakarta Pusat',
            'province' => 'DKI Jakarta',
            'postal_code' => '10220',
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
            'code' => 'WWW-001',
            'name' => 'WWW',
            'brand_name' => 'WWW',
            'email' => 'info@www.co.id',
            'phone' => '+62-21-87654321',
            'address' => 'Jl. Gatot Subroto No. 10, Jakarta Selatan',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'postal_code' => '12190',
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

        $branches = [
            [
                'id' => '8823b1b3-4567-7fbc-4567-890123def123',
                'code' => 'WWW-BDG-001',
                'name' => 'Bandung',
                'brand_name' => 'WWW Bandung',
                'email' => 'bandung@www.co.id',
                'phone' => '+62-22-44556677',
                'address' => 'Jl. Asia Afrika No. 20, Bandung',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'postal_code' => '40111',
                'opening_date' => '2020-06-01',
            ],
            [
                'id' => '6601f9f1-2345-5d9a-2345-678901bcdef1',
                'code' => 'WWW-JKT-001',
                'name' => 'Jakarta',
                'brand_name' => 'WWW Jakarta',
                'email' => 'jakarta@www.co.id',
                'phone' => '+62-21-77889900',
                'address' => 'Jl. Fatmawati No. 15, Jakarta Selatan',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12150',
                'opening_date' => '2020-01-15',
            ],
            [
                'id' => '9934c2c4-5678-8bcd-5678-901234ef1234',
                'code' => 'WWW-SBY-001',
                'name' => 'Surabaya',
                'brand_name' => 'WWW Surabaya',
                'email' => 'surabaya@www.co.id',
                'phone' => '+62-31-33445566',
                'address' => 'Tunjungan Plaza, Level 4, Surabaya',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'postal_code' => '60275',
                'opening_date' => '2020-02-14',
            ],
        ];

        foreach ($branches as $branch) {
            BusinessUnit::create([
                'id' => $branch['id'],
                'parent_id' => $company->id,
                'type_code' => 'BRANCH',
                'code' => $branch['code'],
                'name' => $branch['name'],
                'brand_name' => $branch['brand_name'],
                'email' => $branch['email'],
                'phone' => $branch['phone'],
                'address' => $branch['address'],
                'city' => $branch['city'],
                'province' => $branch['province'],
                'postal_code' => $branch['postal_code'],
                'country' => 'Indonesia',
                'is_pos_active' => true,
                'is_inventory_active' => true,
                'tax_type' => 'PPN',
                'tax_percentage' => 11.00,
                'service_charge_percentage' => 0,
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'opening_date' => $branch['opening_date'],
                'is_active' => true,
            ]);
        }

        $this->command->info('Business Units seeded successfully!');
        $this->command->info('  - Holding: WIT. Indonesia (' . $holding->id . ')');
        $this->command->info('  - Company: WWW (' . $company->id . ')');
        $this->command->info('  - Branches: Bandung, Jakarta, Surabaya');
    }
}
