<?php

use App\Services\MasterData\InventoryWarehouseMigrationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE_VARIANT_STOCK = 'product.product_variant_stock';

    private const TABLE_STOCK_MOVEMENTS = 'product.product_stock_movements';

    private const TABLE_COST_LAYERS = 'product.product_cost_layers';

    private const TABLE_BATCH_STOCK = 'product.product_batch_stock';

    private const TABLE_LEGACY_STOCK = 'product.product_stock';

    public function up(): void
    {
        $this->addWarehouseIdColumns();

        $mapper = app(InventoryWarehouseMigrationService::class);

        foreach ($this->inventoryTables() as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'warehouse_id')) {
                $mapper->migrateInventoryTable($table);
            }
        }

        $this->applyWarehouseForeignKeys();
        $this->replaceUniqueIndexes();
        $this->replaceCostLayerIndex();
    }

    /**
     * @return list<string>
     */
    private function inventoryTables(): array
    {
        $tables = [
            self::TABLE_VARIANT_STOCK,
            self::TABLE_STOCK_MOVEMENTS,
            self::TABLE_COST_LAYERS,
            self::TABLE_BATCH_STOCK,
        ];

        if (Schema::hasTable(self::TABLE_LEGACY_STOCK)) {
            $tables[] = self::TABLE_LEGACY_STOCK;
        }

        return $tables;
    }

    public function down(): void
    {
        $this->dropWarehouseForeignKeys();
        $this->restoreUniqueIndexes();
        $this->restoreCostLayerIndex();

        foreach ([
            self::TABLE_VARIANT_STOCK,
            self::TABLE_STOCK_MOVEMENTS,
            self::TABLE_COST_LAYERS,
            self::TABLE_BATCH_STOCK,
            self::TABLE_LEGACY_STOCK,
        ] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'warehouse_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('warehouse_id');
                });
            }
        }
    }

    private function addWarehouseIdColumns(): void
    {
        $tables = [
            self::TABLE_VARIANT_STOCK,
            self::TABLE_STOCK_MOVEMENTS,
            self::TABLE_COST_LAYERS,
            self::TABLE_BATCH_STOCK,
        ];

        if (Schema::hasTable(self::TABLE_LEGACY_STOCK)) {
            $tables[] = self::TABLE_LEGACY_STOCK;
        }

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'warehouse_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('warehouse_id')->nullable()->after('branch_id');
                $blueprint->index('warehouse_id');
            });
        }
    }

    private function applyWarehouseForeignKeys(): void
    {
        foreach ($this->inventoryTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'warehouse_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('warehouse_id')
                    ->references('id')
                    ->on('master_data.warehouses')
                    ->onDelete('cascade');
            });
        }
    }

    private function dropWarehouseForeignKeys(): void
    {
        foreach ([
            self::TABLE_VARIANT_STOCK => 'product_variant_stock_warehouse_id_foreign',
            self::TABLE_STOCK_MOVEMENTS => 'product_stock_movements_warehouse_id_foreign',
            self::TABLE_COST_LAYERS => 'product_cost_layers_warehouse_id_foreign',
            self::TABLE_BATCH_STOCK => 'product_batch_stock_warehouse_id_foreign',
            self::TABLE_LEGACY_STOCK => 'product_stock_warehouse_id_foreign',
        ] as $table => $constraint) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'warehouse_id')) {
                continue;
            }

            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        }
    }

    private function replaceUniqueIndexes(): void
    {
        if (Schema::hasTable(self::TABLE_VARIANT_STOCK) && Schema::hasColumn(self::TABLE_VARIANT_STOCK, 'warehouse_id')) {
            DB::statement('ALTER TABLE product.product_variant_stock DROP CONSTRAINT IF EXISTS product_variant_stock_product_variant_id_branch_id_unique');
            DB::statement('ALTER TABLE product.product_variant_stock DROP CONSTRAINT IF EXISTS product_product_variant_stock_product_variant_id_branch_id_unique');
            DB::statement('DROP INDEX IF EXISTS product.product_variant_stock_product_variant_id_branch_id_unique');

            Schema::table(self::TABLE_VARIANT_STOCK, function (Blueprint $blueprint) {
                $blueprint->unique(['product_variant_id', 'warehouse_id'], 'product_variant_stock_variant_warehouse_unique');
            });
        }

        if (Schema::hasTable(self::TABLE_BATCH_STOCK) && Schema::hasColumn(self::TABLE_BATCH_STOCK, 'warehouse_id')) {
            DB::statement('ALTER TABLE product.product_batch_stock DROP CONSTRAINT IF EXISTS product_batch_stock_product_batch_id_branch_id_unique');
            DB::statement('ALTER TABLE product.product_batch_stock DROP CONSTRAINT IF EXISTS product_product_batch_stock_product_batch_id_branch_id_unique');
            DB::statement('DROP INDEX IF EXISTS product.product_batch_stock_product_batch_id_branch_id_unique');

            Schema::table(self::TABLE_BATCH_STOCK, function (Blueprint $blueprint) {
                $blueprint->unique(['product_batch_id', 'warehouse_id'], 'product_batch_stock_batch_warehouse_unique');
            });
        }
    }

    private function restoreUniqueIndexes(): void
    {
        DB::statement('ALTER TABLE product.product_variant_stock DROP CONSTRAINT IF EXISTS product_variant_stock_variant_warehouse_unique');
        Schema::table('product.product_variant_stock', function (Blueprint $blueprint) {
            $blueprint->unique(['product_variant_id', 'branch_id']);
        });

        DB::statement('ALTER TABLE product.product_batch_stock DROP CONSTRAINT IF EXISTS product_batch_stock_batch_warehouse_unique');
        Schema::table('product.product_batch_stock', function (Blueprint $blueprint) {
            $blueprint->unique(['product_batch_id', 'branch_id']);
        });
    }

    private function replaceCostLayerIndex(): void
    {
        if (! Schema::hasTable(self::TABLE_COST_LAYERS) || ! Schema::hasColumn(self::TABLE_COST_LAYERS, 'warehouse_id')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS product.product_cost_layers_product_variant_id_branch_id_quantity_remainin');
        DB::statement('DROP INDEX IF EXISTS product.product_cost_layers_product_variant_id_branch_id_quantity_remaining_index');
        DB::statement('CREATE INDEX product_cost_layers_variant_warehouse_remaining_idx ON product.product_cost_layers (product_variant_id, warehouse_id, quantity_remaining)');
    }

    private function restoreCostLayerIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS product.product_cost_layers_variant_warehouse_remaining_idx');
        DB::statement('CREATE INDEX product_cost_layers_product_variant_id_branch_id_quantity_remaining_index ON product.product_cost_layers (product_variant_id, branch_id, quantity_remaining)');
    }
};
