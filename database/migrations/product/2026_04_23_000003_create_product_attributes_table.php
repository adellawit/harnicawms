<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dynamic attribute system - color, size, gender, material, etc.
     * Attribute definitions define the attribute type; values are the options.
     */
    public function up(): void
    {
        Schema::create('product.attribute_definitions', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id')->nullable();

            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('type', 30)->default('select'); // select, multiselect, text, number, boolean
            $table->text('description')->nullable();
            $table->jsonb('validation_rules')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_variant_attribute')->default(false);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_required')->default(false);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('code');
        });

        Schema::table('product.attribute_definitions', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');
        });

        Schema::create('product.attribute_values', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('attribute_definition_id');
            $table->string('value');
            $table->string('code', 50)->nullable();
            $table->string('swatch', 50)->nullable();
            $table->integer('sort_order')->default(0);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['attribute_definition_id', 'value']);
            $table->index('attribute_definition_id');
        });

        Schema::table('product.attribute_values', function (Blueprint $table) {
            $table->foreign('attribute_definition_id')
                ->references('id')
                ->on('product.attribute_definitions')
                ->onDelete('cascade');
        });

        Schema::create('product.product_attributes', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_id');
            $table->uuid('attribute_definition_id');
            $table->uuid('attribute_value_id')->nullable();
            $table->text('value_text')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'attribute_definition_id']);
            $table->index('product_id');
            $table->index('attribute_definition_id');
            $table->index('attribute_value_id');
        });

        Schema::table('product.product_attributes', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
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
        Schema::dropIfExists('product.product_attributes');
        Schema::dropIfExists('product.attribute_values');
        Schema::dropIfExists('product.attribute_definitions');
    }
};
