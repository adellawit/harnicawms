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
            ShippingRateSeeder::class,
            RoleSeeder::class,
            PositionSeeder::class,
            ParameterSeeder::class,
            DivisionSeeder::class,

            // Business units (before employees, karena employees punya FK ke business_units)
            BusinessUnitSeeder::class,

            // Payment methods per branch (depends on: business_units)
            MethodPaymentSeeder::class,
            ManualTransferMethodPaymentSeeder::class,

            // Employees & users (depends on: positions, divisions, business_units, roles)
            EmployeeSeeder::class,
            UserSeeder::class,
            TelegramAccountSeeder::class,

            // Menu and access (depends on: roles, menus)
            MenuSeeder::class,
            IamAccessSeeder::class,
            IamHasAccessSeeder::class,
            TrainingAccessSeeder::class,
            MarketingAccessSeeder::class,
            BarcodeTrackingAccessSeeder::class,
            FgBarcodeStockAccessSeeder::class,
            AgentCuttingPriceAccessSeeder::class,
            ResellerMappingAccessSeeder::class,
            AgenOrderAccessSeeder::class,

            // Product reference data
            StockMutationTypeSeeder::class,
            ProductUnitSeeder::class,

            // Unified Product Master parameters (ITEM_TYPE, PRODUCT_NATURE, PROCUREMENT_TYPE)
            ProductParameterSeeder::class,

            // Default product types (RAW_MATERIAL, SEMI_FINISHED, FINISHED_GOOD, SERVICE, NON_STOCK)
            ProductNatureSeeder::class,

            // Price lists (channel harga)
            ProductPriceListSeeder::class,

            // Sync master_data.warehouses dari business_units WAREHOUSE + default per cabang
            WarehouseSeeder::class,

            // Supplier Foredi (Pabrik + Packaging)
            // Depends on: BusinessUnitSeeder, ParameterSeeder (SUPPLIER)
            ForediSupplierSeeder::class,

            // Foredi Agent & Reseller (+ geocode Alamat Domisili)
            // Depends on: BusinessUnitSeeder (SUHARA-001 / SUHARA-BDG-001)
            ForediPartnerSeeder::class,

            // Foredi RM + FG (conversions, BOM 3→4 sachet/box, stock RM/FG warehouses)
            // Depends on: ProductUnitSeeder (+SACHET), ProductNatureSeeder, ProductParameterSeeder,
            //             ProductPriceListSeeder, WarehouseSeeder, BusinessUnitSeeder
            ForediProductSeeder::class,
        ]);
    }
}
