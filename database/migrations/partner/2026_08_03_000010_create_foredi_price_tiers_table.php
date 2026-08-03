<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner.foredi_price_tiers', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('category_id');
            $table->uuid('product_id');
            $table->string('level', 30);
            $table->decimal('min_qty', 18, 4)->nullable();
            $table->string('unit_code', 20)->default('BOX');
            $table->decimal('price', 18, 4);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('product_id');
            $table->index('level');
            $table->index('is_active');
            $table->index('sort_order');
        });

        Schema::table('partner.foredi_price_tiers', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('product.product_categories')
                ->onDelete('restrict');

            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('restrict');
        });

        // NULL-safe uniqueness for active rows only.
        DB::statement('
            CREATE UNIQUE INDEX foredi_price_tiers_product_level_null_qty_unique
            ON partner.foredi_price_tiers (product_id, level)
            WHERE min_qty IS NULL AND deleted_at IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX foredi_price_tiers_product_level_qty_unique
            ON partner.foredi_price_tiers (product_id, level, min_qty)
            WHERE min_qty IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('partner.foredi_price_tiers');
    }
};
