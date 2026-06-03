<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Unified Product Master - Main products table
     * Semua item: Finished Goods, Raw Material, Semi-Finished, Service, Bundle
     */
    public function up(): void
    {
        Schema::create('product.products', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('nature_id')->nullable();
            $table->uuid('item_type_id')->nullable();
            $table->uuid('product_nature_id')->nullable();
            $table->uuid('procurement_type_id')->nullable();
            $table->uuid('default_unit_id')->nullable();

            $table->string('name');
            $table->string('code')->nullable();
            $table->string('sku', 100)->nullable();
            $table->text('description')->nullable();

            // Enterprise flags (SAP/Oracle style)
            $table->boolean('is_stock_item')->default(true);
            $table->boolean('is_sale_item')->default(false);
            $table->boolean('is_purchase_item')->default(true);

            $table->decimal('min_stock', 18, 4)->default(0);
            $table->decimal('max_stock', 18, 4)->nullable();

            // Accounting integration (COGS & Revenue mapping)
            $table->string('cogs_account_code', 50)->nullable();
            $table->string('revenue_account_code', 50)->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Best practice indexing (multi-company: code unique per company)
            $table->index('code');
            $table->index('sku');
            $table->index(['company_id', 'branch_id']);
            $table->unique(['company_id', 'code'], 'products_company_code_unique');
            $table->index('nature_id');
            $table->index('item_type_id');
            $table->index('product_nature_id');
            $table->index('procurement_type_id');
            $table->index('default_unit_id');
            $table->index(['is_stock_item', 'is_sale_item', 'is_purchase_item']);
        });

        Schema::table('product.products', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('nature_id')
                ->references('id')
                ->on('product.product_natures')
                ->onDelete('set null');

            $table->foreign('item_type_id')
                ->references('id')
                ->on('public.parameter_details')
                ->onDelete('set null');

            $table->foreign('product_nature_id')
                ->references('id')
                ->on('public.parameter_details')
                ->onDelete('set null');

            $table->foreign('procurement_type_id')
                ->references('id')
                ->on('public.parameter_details')
                ->onDelete('set null');

            $table->foreign('default_unit_id')
                ->references('id')
                ->on('product.product_units')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.products');
    }
};
