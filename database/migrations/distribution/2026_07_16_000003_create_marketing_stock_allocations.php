<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS distribution');

        Schema::create('distribution.marketing_stock_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->string('allocation_number', 50)->unique();
            $table->date('allocation_date');
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('from_warehouse_id');
            $table->uuid('to_warehouse_id');
            $table->string('status', 30)->default('completed');
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('allocation_date');
            $table->index('status');
            $table->index('from_warehouse_id');
            $table->index('to_warehouse_id');
        });

        Schema::table('distribution.marketing_stock_allocations', function (Blueprint $table) {
            $table->foreign('from_warehouse_id')->references('id')->on('master_data.warehouses')->onDelete('restrict');
            $table->foreign('to_warehouse_id')->references('id')->on('master_data.warehouses')->onDelete('restrict');
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('master_data.business_units')->onDelete('set null');
        });

        Schema::create('distribution.marketing_stock_allocation_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('allocation_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('unit_id');
            $table->decimal('quantity', 18, 6)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->timestamps();

            $table->index('allocation_id');
            $table->index('product_variant_id');
        });

        Schema::table('distribution.marketing_stock_allocation_items', function (Blueprint $table) {
            $table->foreign('allocation_id')
                ->references('id')
                ->on('distribution.marketing_stock_allocations')
                ->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('product.products')->onDelete('restrict');
            $table->foreign('product_variant_id')->references('id')->on('product.product_variants')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('product.product_units')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution.marketing_stock_allocation_items');
        Schema::dropIfExists('distribution.marketing_stock_allocations');
    }
};
