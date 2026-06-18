<?php

use App\Services\MasterData\InventoryWarehouseMigrationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * DDL + row updates must share one connection; avoid cross-connection lock waits.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('ALTER TABLE transaction.sales_orders DROP CONSTRAINT IF EXISTS sales_orders_warehouse_id_foreign');

        app(InventoryWarehouseMigrationService::class)->migrateSalesOrderWarehouseReferences();

        DB::statement('
            ALTER TABLE transaction.sales_orders
            ADD CONSTRAINT sales_orders_warehouse_id_foreign
            FOREIGN KEY (warehouse_id) REFERENCES master_data.warehouses(id) ON DELETE SET NULL
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE transaction.sales_orders DROP CONSTRAINT IF EXISTS sales_orders_warehouse_id_foreign');

        DB::statement('
            ALTER TABLE transaction.sales_orders
            ADD CONSTRAINT sales_orders_warehouse_id_foreign
            FOREIGN KEY (warehouse_id) REFERENCES master_data.business_units(id) ON DELETE SET NULL
        ');
    }
};
