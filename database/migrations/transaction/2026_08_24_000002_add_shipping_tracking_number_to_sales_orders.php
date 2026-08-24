<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            $table->string('shipping_tracking_number', 100)->nullable()->after('shipping_etd');
        });
    }

    public function down(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            $table->dropColumn('shipping_tracking_number');
        });
    }
};
