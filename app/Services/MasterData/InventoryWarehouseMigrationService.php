<?php

namespace App\Services\MasterData;

use Illuminate\Support\Facades\DB;

/**
 * Data migration helpers: map legacy branch_id (often warehouse BU id) to warehouse_id + branch_id.
 */
class InventoryWarehouseMigrationService
{
    private const TABLE_WAREHOUSES = 'warehouses';

    private const TABLE_SALES_ORDERS = 'sales_orders';

    private const TABLE_PRODUCTION_ORDERS = 'manufacturing.production_orders';

    private const COL_ID = 'id';

    private const COL_BRANCH_ID = 'branch_id';

    private const COL_WAREHOUSE_ID = 'warehouse_id';

    private const COL_LEGACY_BUSINESS_UNIT_ID = 'legacy_business_unit_id';

    private const COL_OUTPUT_WAREHOUSE_ID = 'output_warehouse_id';

    private const COL_SOURCE_WAREHOUSE_ID = 'source_warehouse_id';

    public function migrateInventoryTable(string $qualifiedTable, bool $updateBranchFromWarehouse = true): void
    {
        $table = $this->tableName($qualifiedTable);

        $this->mapWarehouseFromLegacyBusinessUnit($table);
        $this->mapDefaultWarehouseForBranch($table);

        if ($updateBranchFromWarehouse) {
            $this->syncBranchFromWarehouse($table);
        }
    }

    public function mapWarehouseFkValue(?string $legacyId): ?string
    {
        if (! $legacyId) {
            return null;
        }

        $row = DB::table('master_data.'.self::TABLE_WAREHOUSES)
            ->where(self::COL_ID, $legacyId)
            ->orWhere(self::COL_LEGACY_BUSINESS_UNIT_ID, $legacyId)
            ->first();

        return $row?->id;
    }

    public function migrateSalesOrderWarehouseReferences(): void
    {
        DB::table('transaction.'.self::TABLE_SALES_ORDERS)
            ->whereNotNull(self::COL_WAREHOUSE_ID)
            ->orderBy(self::COL_ID)
            ->chunkById(500, function ($orders) {
                foreach ($orders as $order) {
                    $mapped = $this->mapWarehouseFkValue($order->warehouse_id);

                    if ($mapped && $mapped !== $order->warehouse_id) {
                        DB::table('transaction.'.self::TABLE_SALES_ORDERS)
                            ->where(self::COL_ID, $order->id)
                            ->update([self::COL_WAREHOUSE_ID => $mapped]);
                    }
                }
            }, self::COL_ID);
    }

    public function migrateProductionOrderWarehouseReferences(): void
    {
        DB::table(self::TABLE_PRODUCTION_ORDERS)
            ->orderBy(self::COL_ID)
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    $outputId = $this->mapWarehouseFkValue($order->output_warehouse_id);
                    $sourceId = $this->mapWarehouseFkValue($order->source_warehouse_id);

                    $branchId = $order->branch_id;
                    if ($outputId) {
                        $branchId = DB::table('master_data.'.self::TABLE_WAREHOUSES)
                            ->where(self::COL_ID, $outputId)
                            ->value(self::COL_BRANCH_ID) ?? $branchId;
                    }

                    DB::table(self::TABLE_PRODUCTION_ORDERS)
                        ->where(self::COL_ID, $order->id)
                        ->update([
                            self::COL_OUTPUT_WAREHOUSE_ID => $outputId ?? $order->output_warehouse_id,
                            self::COL_SOURCE_WAREHOUSE_ID => $sourceId ?? $order->source_warehouse_id,
                            self::COL_BRANCH_ID => $branchId,
                        ]);
                }
            }, self::COL_ID);
    }

    private function mapWarehouseFromLegacyBusinessUnit(string $table): void
    {
        DB::statement("
            UPDATE {$table} AS t
            SET warehouse_id = w.id
            FROM master_data.warehouses AS w
            WHERE t.warehouse_id IS NULL
              AND (w.id = t.branch_id OR w.legacy_business_unit_id = t.branch_id)
        ");
    }

    private function mapDefaultWarehouseForBranch(string $table): void
    {
        DB::statement("
            UPDATE {$table} AS t
            SET warehouse_id = w.id
            FROM master_data.warehouses AS w
            WHERE t.warehouse_id IS NULL
              AND w.branch_id = t.branch_id
              AND w.is_default = true
              AND w.deleted_at IS NULL
        ");

        DB::statement("
            UPDATE {$table} AS t
            SET warehouse_id = sub.id
            FROM (
                SELECT DISTINCT ON (branch_id) branch_id, id
                FROM master_data.warehouses
                WHERE deleted_at IS NULL
                  AND branch_id IS NOT NULL
                ORDER BY branch_id, is_default DESC, created_at
            ) AS sub
            WHERE t.warehouse_id IS NULL
              AND sub.branch_id = t.branch_id
        ");
    }

    private function syncBranchFromWarehouse(string $table): void
    {
        DB::statement("
            UPDATE {$table} AS t
            SET branch_id = w.branch_id
            FROM master_data.warehouses AS w
            WHERE t.warehouse_id = w.id
              AND w.branch_id IS NOT NULL
        ");
    }

    private function tableName(string $qualifiedTable): string
    {
        return str_contains($qualifiedTable, '.')
            ? $qualifiedTable
            : 'product.'.$qualifiedTable;
    }
}
