<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner.cutting_price_configs', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('category_id');
            $table->uuid('product_id');
            $table->string('unit_code', 20)->default('BOX');
            $table->decimal('official_price', 18, 4);
            $table->decimal('map_price', 18, 4);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('product_id');
            $table->index('is_active');
            $table->index('sort_order');
        });

        Schema::table('partner.cutting_price_configs', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('product.product_categories')
                ->onDelete('restrict');

            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('restrict');
        });

        DB::statement('
            CREATE UNIQUE INDEX cutting_price_configs_product_unit_unique
            ON partner.cutting_price_configs (product_id, unit_code)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('partner.cutting_price_configs');
    }
};
