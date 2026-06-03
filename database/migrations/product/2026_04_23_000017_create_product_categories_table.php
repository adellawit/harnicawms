<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hierarchical product categories (taxonomy)
     * Retail: Electronics > Phones > Smartphones
     * F&B: Beverages > Coffee > Espresso
     * Manufacturing: Components > Electrical > Motors
     */
    public function up(): void
    {
        Schema::create('product.product_categories', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('parent_id')->nullable();

            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('slug', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('image', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('path', 500)->nullable(); // materialized path for tree queries

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('branch_id');
            $table->index('parent_id');
            $table->index('code');
            $table->index('slug');
            $table->index('path');
        });

        Schema::table('product.product_categories', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('parent_id')
                ->references('id')
                ->on('product.product_categories')
                ->onDelete('set null');
        });

        // Add category_id to products
        Schema::table('product.products', function (Blueprint $table) {
            $table->uuid('category_id')->nullable()->after('nature_id');
        });

        Schema::table('product.products', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('product.product_categories')
                ->onDelete('set null');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('product.products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('product.product_categories');
    }
};
