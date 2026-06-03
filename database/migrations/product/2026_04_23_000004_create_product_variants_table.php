<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Product variants - SKU-level variations (Size S/M/L, Color Red/Blue)
     * Variant = product + combination of attribute values
     */
    public function up(): void
    {
        Schema::create('product.product_variants', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_id');
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 100)->nullable();

            $table->decimal('price_adjustment', 18, 4)->default(0); // offset from base product
            $table->decimal('cost_adjustment', 18, 4)->default(0)->nullable();
            $table->decimal('weight', 18, 6)->nullable();
            $table->string('image', 255)->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('sku');
            $table->index('barcode');
            $table->unique(['product_id', 'sku']);
        });

        Schema::table('product.product_variants', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('cascade');
        });

        // Variant = product + attribute value combination
        Schema::create('product.product_variant_attributes', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_variant_id');
            $table->uuid('attribute_definition_id');
            $table->uuid('attribute_value_id');

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->timestamps();

            $table->unique(['product_variant_id', 'attribute_definition_id']);
            $table->index('product_variant_id');
            $table->index('attribute_definition_id');
            $table->index('attribute_value_id');
        });

        Schema::table('product.product_variant_attributes', function (Blueprint $table) {
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product.product_variants')
                ->onDelete('cascade');

            $table->foreign('attribute_definition_id')
                ->references('id')
                ->on('product.attribute_definitions')
                ->onDelete('cascade');

            $table->foreign('attribute_value_id')
                ->references('id')
                ->on('product.attribute_values')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.product_variant_attributes');
        Schema::dropIfExists('product.product_variants');
    }
};
