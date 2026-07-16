<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm.membership_point_configurations', function (Blueprint $table) {
            $table->unsignedBigInteger('redeem_value_per_point')
                ->default(100)
                ->after('points_per_step')
                ->comment('Rupiah discount value per 1 redeemed point');
        });

        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            $table->bigInteger('membership_points_redeemed')->default(0)->after('membership_points_earned');
            $table->decimal('membership_redeem_discount_amount', 18, 4)->default(0)->after('membership_points_redeemed');
        });
    }

    public function down(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            $table->dropColumn(['membership_points_redeemed', 'membership_redeem_discount_amount']);
        });

        Schema::table('crm.membership_point_configurations', function (Blueprint $table) {
            $table->dropColumn('redeem_value_per_point');
        });
    }
};
