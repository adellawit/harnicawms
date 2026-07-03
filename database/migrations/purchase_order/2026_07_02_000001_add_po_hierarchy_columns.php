<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('product.purchase_orders', 'parent_id')) {
                $table->uuid('parent_id')->nullable()->after('warehouse_id');
                $table->string('po_kind', 20)->default('standalone')->after('parent_id');
                $table->unsignedSmallInteger('release_sequence')->nullable()->after('po_kind');
                $table->string('release_status', 20)->nullable()->after('release_sequence');

                $table->index('parent_id');
                $table->index('po_kind');
            }
        });

        Schema::table('product.purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('product.purchase_orders', 'parent_id')) {
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('product.purchase_orders')
                    ->onDelete('restrict');
            }
        });

        Schema::table('product.purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('product.purchase_order_items', 'parent_item_id')) {
                $table->uuid('parent_item_id')->nullable()->after('purchase_order_id');
                $table->index('parent_item_id');
            }
        });

        Schema::table('product.purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('product.purchase_order_items', 'parent_item_id')) {
                $table->foreign('parent_item_id')
                    ->references('id')
                    ->on('product.purchase_order_items')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product.purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('product.purchase_order_items', 'parent_item_id')) {
                $table->dropForeign(['parent_item_id']);
                $table->dropColumn('parent_item_id');
            }
        });

        Schema::table('product.purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('product.purchase_orders', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn(['parent_id', 'po_kind', 'release_sequence', 'release_status']);
            }
        });
    }
};
