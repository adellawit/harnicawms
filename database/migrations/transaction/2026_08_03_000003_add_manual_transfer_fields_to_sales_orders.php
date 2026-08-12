<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('transaction.sales_orders', 'unique_code')) {
                $table->integer('unique_code')->nullable()->after('total');
            }
            if (! Schema::hasColumn('transaction.sales_orders', 'payable_amount')) {
                $table->decimal('payable_amount', 18, 4)->nullable()->after('unique_code');
            }
            if (! Schema::hasColumn('transaction.sales_orders', 'payment_proof_path')) {
                $table->string('payment_proof_path')->nullable()->after('payable_amount');
            }
            if (! Schema::hasColumn('transaction.sales_orders', 'payment_proof_uploaded_at')) {
                $table->timestamp('payment_proof_uploaded_at')->nullable()->after('payment_proof_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            foreach (['payment_proof_uploaded_at', 'payment_proof_path', 'payable_amount', 'unique_code'] as $column) {
                if (Schema::hasColumn('transaction.sales_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
