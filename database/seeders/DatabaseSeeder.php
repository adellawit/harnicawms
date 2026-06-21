<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Database\Seeders\Telegram\AccountSeeder as TelegramAccountSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core/reference data first (no FK dependencies)
            ProvinceSeeder::class,
            CitySeeder::class,
            RoleSeeder::class,
            PositionSeeder::class,
            ParameterSeeder::class,
            DivisionSeeder::class,

            // Business units (before employees, karena employees punya FK ke business_units)
            BusinessUnitSeeder::class,

            // Payment methods per branch (depends on: business_units)
            MethodPaymentSeeder::class,

            // Employees & users (depends on: positions, divisions, business_units, roles)
            EmployeeSeeder::class,
            UserSeeder::class,
            TelegramAccountSeeder::class,

            // Menu and access (depends on: roles, menus)
            MenuSeeder::class,
            AgentMenuSeeder::class,
            AiConfigurationMenuSeeder::class,
            ManufacturingMenuSeeder::class,
            IamAccessSeeder::class,
            IamHasAccessSeeder::class,
            WarehouseMenuSeeder::class,
            PartnerNetworkMenuSeeder::class,

            // Product reference data
            StockMutationTypeSeeder::class,
            ProductUnitSeeder::class,

            // Unified Product Master parameters (ITEM_TYPE, PRODUCT_NATURE, PROCUREMENT_TYPE)
            ProductParameterSeeder::class,

            // Default product types (RAW_MATERIAL, SEMI_FINISHED, FINISHED_GOOD, SERVICE, NON_STOCK)
            ProductNatureSeeder::class,

            // Customer groups & karyawan (requires business_units)
            CustomerSeeder::class,

            // Price lists (channel harga)
            ProductPriceListSeeder::class,
            // ProductMenuSeeder::class, // dinonaktifkan: 49 produk menu kopi tidak dipakai (fokus herbal)

            // Demo: rantai Distributor -> Agen + Produksi herbal (HPP FEFO, 2 gudang)
            DistributionDemoSeeder::class,

            // Sync master_data.warehouses dari business_units WAREHOUSE + default per cabang
            WarehouseSeeder::class,

            // Demo: produk manufaktur sepatu + BOM dari product_manufacture.json
            ManufacturingBomSeeder::class,
        ]);
    }
}
