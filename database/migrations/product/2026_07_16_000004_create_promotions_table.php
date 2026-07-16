<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS product');

        Schema::create('product.promotions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // line | order (phase 1 uses line)
            $table->string('trigger_level', 20)->default('line');
            $table->unsignedInteger('priority')->default(100);

            // Buy condition
            $table->decimal('buy_min_qty', 18, 6)->default(1);
            $table->uuid('buy_product_id')->nullable();
            $table->uuid('buy_variant_id')->nullable();

            // Get reward
            $table->decimal('get_qty', 18, 6)->default(1);
            // same = same as buy line; specific = get_product/variant
            $table->string('get_product_mode', 20)->default('same');
            $table->uuid('get_product_id')->nullable();
            $table->uuid('get_variant_id')->nullable();
            $table->uuid('get_unit_id')->nullable();

            // Warehouse sourcing for free items: FG | MARKETING | ORDER
            $table->string('free_warehouse_type', 30)->default('MARKETING');
            $table->unsignedInteger('max_applications_per_line')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index('buy_product_id');
            $table->index('buy_variant_id');
        });

        Schema::table('product.promotions', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('set null');
            $table->foreign('buy_product_id')->references('id')->on('product.products')->onDelete('set null');
            $table->foreign('buy_variant_id')->references('id')->on('product.product_variants')->onDelete('set null');
            $table->foreign('get_product_id')->references('id')->on('product.products')->onDelete('set null');
            $table->foreign('get_variant_id')->references('id')->on('product.product_variants')->onDelete('set null');
            $table->foreign('get_unit_id')->references('id')->on('product.product_units')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.promotions');
    }
};
