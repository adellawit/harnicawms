<?php

use App\Services\MasterData\InventoryWarehouseMigrationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'manufacturing.production_orders';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (Schema::hasColumn(self::TABLE, 'branch_id') && ! Schema::hasColumn(self::TABLE, 'output_warehouse_id')) {
            DB::statement('ALTER TABLE manufacturing.production_orders DROP CONSTRAINT IF EXISTS production_orders_branch_id_foreign');
            DB::statement('ALTER TABLE manufacturing.production_orders DROP CONSTRAINT IF EXISTS production_orders_source_warehouse_id_foreign');

            DB::statement('ALTER TABLE manufacturing.production_orders RENAME COLUMN branch_id TO output_warehouse_id');
            DB::statement('ALTER INDEX IF EXISTS manufacturing.manufacturing_production_orders_branch_id_index RENAME TO manufacturing_production_orders_output_warehouse_id_idx');
        }

        if (! Schema::hasColumn(self::TABLE, 'branch_id')) {
            DB::statement('ALTER TABLE manufacturing.production_orders ADD COLUMN branch_id uuid NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS manufacturing_production_orders_operational_branch_id_idx ON manufacturing.production_orders (branch_id)');
        }

        app(InventoryWarehouseMigrationService::class)->migrateProductionOrderWarehouseReferences();

        DB::statement('ALTER TABLE manufacturing.production_orders DROP CONSTRAINT IF EXISTS production_orders_branch_id_foreign');
        DB::statement('ALTER TABLE manufacturing.production_orders DROP CONSTRAINT IF EXISTS production_orders_output_warehouse_id_foreign');
        DB::statement('ALTER TABLE manufacturing.production_orders DROP CONSTRAINT IF EXISTS production_orders_source_warehouse_id_foreign');

        DB::statement('
            ALTER TABLE manufacturing.production_orders
            ADD CONSTRAINT production_orders_branch_id_foreign
            FOREIGN KEY (branch_id) REFERENCES master_data.business_units(id) ON DELETE SET NULL
        ');

        DB::statement('
            ALTER TABLE manufacturing.production_orders
            ADD CONSTRAINT production_orders_output_warehouse_id_foreign
            FOREIGN KEY (output_warehouse_id) REFERENCES master_data.warehouses(id) ON DELETE CASCADE
        ');

        DB::statement('
            ALTER TABLE manufacturing.production_orders
            ADD CONSTRAINT production_orders_source_warehouse_id_foreign
            FOREIGN KEY (source_warehouse_id) REFERENCES master_data.warehouses(id) ON DELETE SET NULL
        ');
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        DB::statement('ALTER TABLE manufacturing.production_orders DROP CONSTRAINT IF EXISTS production_orders_branch_id_foreign');
        DB::statement('ALTER TABLE manufacturing.production_orders DROP CONSTRAINT IF EXISTS production_orders_output_warehouse_id_foreign');
        DB::statement('ALTER TABLE manufacturing.production_orders DROP CONSTRAINT IF EXISTS production_orders_source_warehouse_id_foreign');

        if (Schema::hasColumn(self::TABLE, 'branch_id') && Schema::hasColumn(self::TABLE, 'output_warehouse_id')) {
            DB::statement('
                UPDATE manufacturing.production_orders
                SET output_warehouse_id = COALESCE(output_warehouse_id, branch_id)
            ');

            DB::statement('DROP INDEX IF EXISTS manufacturing.manufacturing_production_orders_operational_branch_id_idx');
            DB::statement('ALTER TABLE manufacturing.production_orders DROP COLUMN branch_id');
            DB::statement('ALTER TABLE manufacturing.production_orders RENAME COLUMN output_warehouse_id TO branch_id');
            DB::statement('ALTER INDEX IF EXISTS manufacturing.manufacturing_production_orders_output_warehouse_id_idx RENAME TO manufacturing_production_orders_branch_id_index');

            DB::statement('
                ALTER TABLE manufacturing.production_orders
                ADD CONSTRAINT production_orders_branch_id_foreign
                FOREIGN KEY (branch_id) REFERENCES master_data.business_units(id) ON DELETE CASCADE
            ');

            DB::statement('
                ALTER TABLE manufacturing.production_orders
                ADD CONSTRAINT production_orders_source_warehouse_id_foreign
                FOREIGN KEY (source_warehouse_id) REFERENCES master_data.business_units(id) ON DELETE SET NULL
            ');
        }
    }
};
