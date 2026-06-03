<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS crm');

        Schema::table('customer.customers', function (Blueprint $table) {
            $table->bigInteger('points_balance')->default(0)->after('notes');
            $table->bigInteger('total_points_earned')->default(0)->after('points_balance');
            $table->bigInteger('total_points_redeemed')->default(0)->after('total_points_earned');
        });

        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            $table->bigInteger('membership_points_earned')->default(0)->after('total');
            $table->uuid('membership_configuration_id')->nullable()->after('membership_points_earned');
        });

        Schema::create('crm.customer_membership_points', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('customer_id');
            $table->uuid('branch_id')->nullable();
            $table->uuid('sales_order_id')->nullable();
            $table->uuid('membership_configuration_id')->nullable();
            $table->string('type', 20)->default('earn');
            $table->bigInteger('points');
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index('sales_order_id');
            $table->index(['customer_id', 'type']);
            $table->unique(['sales_order_id', 'type'], 'crm_customer_membership_points_sales_type_unique');
        });

        Schema::table('crm.customer_membership_points', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer.customers')
                ->onDelete('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('sales_order_id')
                ->references('id')
                ->on('transaction.sales_orders')
                ->onDelete('set null');

            $table->foreign('membership_configuration_id')
                ->references('id')
                ->on('crm.membership_point_configurations')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm.customer_membership_points');

        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            $table->dropColumn(['membership_points_earned', 'membership_configuration_id']);
        });

        Schema::table('customer.customers', function (Blueprint $table) {
            $table->dropColumn(['points_balance', 'total_points_earned', 'total_points_redeemed']);
        });
    }
};

