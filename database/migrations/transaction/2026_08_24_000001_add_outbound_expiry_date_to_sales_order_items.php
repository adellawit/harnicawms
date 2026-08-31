<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction.sales_order_items', function (Blueprint $table) {
            $table->date('outbound_expiry_date')->nullable()->after('source_warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('transaction.sales_order_items', function (Blueprint $table) {
            $table->dropColumn('outbound_expiry_date');
        });
    }
};
