<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Unit conversion per product.
     * Formula: 1 from_unit = conversion_factor * to_unit
     * Example: 1 Dus = 24 Botol
     */
    public function up(): void
    {
        Schema::create('product.product_unit_conversions', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_id');
            $table->uuid('from_unit_id');
            $table->uuid('to_unit_id');
            $table->decimal('conversion_factor', 18, 6);
            $table->integer('conversion_level')->default(1);
            $table->text('description')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'from_unit_id', 'to_unit_id']);
            $table->index('product_id');
            $table->index('from_unit_id');
            $table->index('to_unit_id');
        });

        Schema::table('product.product_unit_conversions', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('cascade');

            $table->foreign('from_unit_id')
                ->references('id')
                ->on('product.product_units')
                ->onDelete('cascade');

            $table->foreign('to_unit_id')
                ->references('id')
                ->on('product.product_units')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.product_unit_conversions');
    }
};
