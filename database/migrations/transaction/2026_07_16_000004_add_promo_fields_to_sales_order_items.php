<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction.sales_order_items', function (Blueprint $table) {
            $table->boolean('is_promo_free')->default(false)->after('subtotal');
            $table->uuid('promotion_id')->nullable()->after('is_promo_free');
            $table->uuid('source_warehouse_id')->nullable()->after('promotion_id');
            $table->uuid('parent_item_id')->nullable()->after('source_warehouse_id');
        });

        Schema::table('transaction.sales_order_items', function (Blueprint $table) {
            $table->foreign('promotion_id')->references('id')->on('product.promotions')->onDelete('set null');
            $table->foreign('source_warehouse_id')->references('id')->on('master_data.warehouses')->onDelete('set null');
            $table->foreign('parent_item_id')->references('id')->on('transaction.sales_order_items')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('transaction.sales_order_items', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropForeign(['source_warehouse_id']);
            $table->dropForeign(['parent_item_id']);
            $table->dropColumn(['is_promo_free', 'promotion_id', 'source_warehouse_id', 'parent_item_id']);
        });
    }
};
