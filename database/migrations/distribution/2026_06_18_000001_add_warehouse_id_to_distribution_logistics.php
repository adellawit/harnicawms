<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('distribution.shipments') || ! Schema::hasTable('distribution.receipts')) {
            return;
        }

        if (! Schema::hasColumn('distribution.shipments', 'source_warehouse_id')) {
            Schema::table('distribution.shipments', function (Blueprint $table) {
                $table->uuid('source_warehouse_id')->nullable()->after('order_id');
                $table->uuid('destination_warehouse_id')->nullable()->after('source_warehouse_id');
                $table->index('source_warehouse_id');
                $table->index('destination_warehouse_id');
            });
        }

        if (! Schema::hasColumn('distribution.receipts', 'warehouse_id')) {
            Schema::table('distribution.receipts', function (Blueprint $table) {
                $table->uuid('warehouse_id')->nullable()->after('shipment_id');
                $table->index('warehouse_id');
            });
        }

        $this->backfillDistributionWarehouses();

        Schema::table('distribution.shipments', function (Blueprint $table) {
            $table->foreign('source_warehouse_id')
                ->references('id')
                ->on('master_data.warehouses')
                ->onDelete('set null');

            $table->foreign('destination_warehouse_id')
                ->references('id')
                ->on('master_data.warehouses')
                ->onDelete('set null');
        });

        Schema::table('distribution.receipts', function (Blueprint $table) {
            $table->foreign('warehouse_id')
                ->references('id')
                ->on('master_data.warehouses')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('distribution.receipts', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });

        Schema::table('distribution.shipments', function (Blueprint $table) {
            $table->dropForeign(['source_warehouse_id']);
            $table->dropForeign(['destination_warehouse_id']);
            $table->dropColumn(['source_warehouse_id', 'destination_warehouse_id']);
        });
    }

    private function backfillDistributionWarehouses(): void
    {
        DB::statement("
            UPDATE distribution.shipments AS s
            SET source_warehouse_id = sub.warehouse_id
            FROM (
                SELECT s2.id AS shipment_id,
                       (
                           SELECT w.id
                           FROM master_data.warehouses AS w
                           WHERE w.company_id = ro.distributor_id
                             AND w.warehouse_type_code = 'FG'
                             AND w.deleted_at IS NULL
                           ORDER BY w.is_default DESC, w.created_at
                           LIMIT 1
                       ) AS warehouse_id
                FROM distribution.shipments AS s2
                INNER JOIN distribution.replenishment_orders AS ro ON ro.id = s2.order_id
            ) AS sub
            WHERE s.id = sub.shipment_id
              AND s.source_warehouse_id IS NULL
              AND sub.warehouse_id IS NOT NULL
        ");

        DB::statement("
            UPDATE distribution.shipments AS s
            SET destination_warehouse_id = w.id
            FROM distribution.replenishment_orders AS ro
            INNER JOIN master_data.warehouses AS w
                ON w.branch_id = ro.agent_id
               AND w.deleted_at IS NULL
            WHERE s.order_id = ro.id
              AND s.destination_warehouse_id IS NULL
              AND w.is_default = true
        ");

        DB::statement("
            UPDATE distribution.receipts AS r
            SET warehouse_id = s.destination_warehouse_id
            FROM distribution.shipments AS s
            WHERE r.shipment_id = s.id
              AND r.warehouse_id IS NULL
              AND s.destination_warehouse_id IS NOT NULL
        ");
    }
};
