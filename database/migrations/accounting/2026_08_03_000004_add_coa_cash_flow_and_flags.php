<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting.chart_of_accounts', function (Blueprint $table) {
            $table->string('cash_flow_category', 30)->nullable()->after('normal_balance');
            $table->boolean('is_contra_account')->default(false)->after('is_header');
            $table->boolean('is_cash_bank')->default(false)->after('is_contra_account');

            $table->index('cash_flow_category');
            $table->index('is_cash_bank');
        });
    }

    public function down(): void
    {
        Schema::table('accounting.chart_of_accounts', function (Blueprint $table) {
            $table->dropIndex(['cash_flow_category']);
            $table->dropIndex(['is_cash_bank']);
            $table->dropColumn(['cash_flow_category', 'is_contra_account', 'is_cash_bank']);
        });
    }
};
