<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction.sales_order_payments', function (Blueprint $table) {
            $table->string('gateway', 50)->nullable()->after('payment_code');
            $table->string('gateway_reference', 100)->nullable()->after('gateway');
            $table->text('gateway_url')->nullable()->after('gateway_reference');
        });
    }

    public function down(): void
    {
        Schema::table('transaction.sales_order_payments', function (Blueprint $table) {
            $table->dropColumn(['gateway', 'gateway_reference', 'gateway_url']);
        });
    }
};
