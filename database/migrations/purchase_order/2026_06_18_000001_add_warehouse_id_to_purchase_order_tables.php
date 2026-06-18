<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE_PURCHASE_ORDERS = 'product.purchase_orders';

    private const TABLE_PURCHASE_RECEIVES = 'product.purchase_order_receives';

    public function up(): void
    {
        if (! Schema::hasColumn(self::TABLE_PURCHASE_ORDERS, 'warehouse_id')) {
            Schema::table(self::TABLE_PURCHASE_ORDERS, function (Blueprint $table) {
                $table->uuid('warehouse_id')->nullable()->after('branch_id');
                $table->index('warehouse_id');
            });
        }

        if (! Schema::hasColumn(self::TABLE_PURCHASE_RECEIVES, 'warehouse_id')) {
            Schema::table(self::TABLE_PURCHASE_RECEIVES, function (Blueprint $table) {
                $table->uuid('warehouse_id')->nullable()->after('purchase_order_id');
                $table->index('warehouse_id');
            });
        }

        $this->backfillPurchaseOrderWarehouses();

        Schema::table(self::TABLE_PURCHASE_ORDERS, function (Blueprint $table) {
            $table->foreign('warehouse_id')
                ->references('id')
                ->on('master_data.warehouses')
                ->onDelete('set null');
        });

        Schema::table(self::TABLE_PURCHASE_RECEIVES, function (Blueprint $table) {
            $table->foreign('warehouse_id')
                ->references('id')
                ->on('master_data.warehouses')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE_PURCHASE_RECEIVES, function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });

        Schema::table(self::TABLE_PURCHASE_ORDERS, function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }

    private function backfillPurchaseOrderWarehouses(): void
    {
        DB::statement("
            UPDATE product.purchase_orders AS po
            SET warehouse_id = w.id
            FROM master_data.warehouses AS w
            WHERE po.warehouse_id IS NULL
              AND po.branch_id IS NOT NULL
              AND w.branch_id = po.branch_id
              AND w.is_default = true
              AND w.deleted_at IS NULL
        ");

        DB::statement("
            UPDATE product.purchase_orders AS po
            SET warehouse_id = sub.id
            FROM (
                SELECT DISTINCT ON (branch_id) branch_id, id
                FROM master_data.warehouses
                WHERE deleted_at IS NULL AND branch_id IS NOT NULL
                ORDER BY branch_id, is_default DESC, created_at
            ) AS sub
            WHERE po.warehouse_id IS NULL
              AND po.branch_id IS NOT NULL
              AND sub.branch_id = po.branch_id
        ");

        DB::statement("
            UPDATE product.purchase_order_receives AS pr
            SET warehouse_id = po.warehouse_id
            FROM product.purchase_orders AS po
            WHERE pr.warehouse_id IS NULL
              AND pr.purchase_order_id = po.id
              AND po.warehouse_id IS NOT NULL
        ");
    }
};
