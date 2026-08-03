<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('transaction.sales_orders', 'shipping_courier')) {
                $table->string('shipping_courier', 30)->nullable()->after('shipping_amount');
            }
            if (! Schema::hasColumn('transaction.sales_orders', 'shipping_service')) {
                $table->string('shipping_service', 30)->nullable()->after('shipping_courier');
            }
            if (! Schema::hasColumn('transaction.sales_orders', 'shipping_rate_id')) {
                $table->uuid('shipping_rate_id')->nullable()->after('shipping_service');
            }
            if (! Schema::hasColumn('transaction.sales_orders', 'shipping_etd')) {
                $table->string('shipping_etd', 30)->nullable()->after('shipping_rate_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            foreach (['shipping_etd', 'shipping_rate_id', 'shipping_service', 'shipping_courier'] as $column) {
                if (Schema::hasColumn('transaction.sales_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
