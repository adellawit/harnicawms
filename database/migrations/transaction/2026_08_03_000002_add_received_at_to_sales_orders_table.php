<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('transaction.sales_orders', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('fulfilled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('transaction.sales_orders', 'received_at')) {
                $table->dropColumn('received_at');
            }
        });
    }
};
