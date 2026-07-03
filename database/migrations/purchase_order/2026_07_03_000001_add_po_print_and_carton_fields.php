<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('product.purchase_orders', 'attention_to')) {
                $table->string('attention_to', 200)->nullable()->after('supplier_address');
            }
            if (! Schema::hasColumn('product.purchase_orders', 'ship_to_address')) {
                $table->text('ship_to_address')->nullable()->after('attention_to');
            }
            if (! Schema::hasColumn('product.purchase_orders', 'other_cost_amount')) {
                $table->decimal('other_cost_amount', 18, 4)->default(0)->after('discount_amount');
            }
        });

        Schema::table('product.purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('product.purchase_order_items', 'carton_qty')) {
                $table->decimal('carton_qty', 18, 6)->nullable()->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product.purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('product.purchase_order_items', 'carton_qty')) {
                $table->dropColumn('carton_qty');
            }
        });

        Schema::table('product.purchase_orders', function (Blueprint $table) {
            foreach (['attention_to', 'ship_to_address', 'other_cost_amount'] as $column) {
                if (Schema::hasColumn('product.purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
