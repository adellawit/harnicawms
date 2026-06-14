<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Manufacturing schema - Bill of Materials (resep produksi).
     * Hanya digunakan sisi Distributor (company).
     */
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS manufacturing');

        // === Bill of Materials (header resep) ===
        Schema::create('manufacturing.bill_of_materials', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id')->nullable();
            $table->uuid('product_id');                 // produk jadi yang dihasilkan
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('output_unit_id');
            $table->decimal('output_quantity', 18, 6)->default(1); // hasil per 1x resep
            $table->string('name', 150);
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('product_id');
            $table->index('product_variant_id');
        });

        Schema::table('manufacturing.bill_of_materials', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('product.products')->onDelete('cascade');
            $table->foreign('product_variant_id')->references('id')->on('product.product_variants')->onDelete('set null');
            $table->foreign('output_unit_id')->references('id')->on('product.product_units')->onDelete('restrict');
        });

        // === BOM Items (komponen / bahan baku) ===
        Schema::create('manufacturing.bom_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('bom_id');
            $table->uuid('component_product_id');
            $table->uuid('component_variant_id')->nullable();
            $table->uuid('unit_id');
            $table->decimal('quantity', 18, 6);        // qty bahan per output_quantity resep
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->timestamps();

            $table->index('bom_id');
            $table->index('component_product_id');
            $table->index('component_variant_id');
        });

        Schema::table('manufacturing.bom_items', function (Blueprint $table) {
            $table->foreign('bom_id')->references('id')->on('manufacturing.bill_of_materials')->onDelete('cascade');
            $table->foreign('component_product_id')->references('id')->on('product.products')->onDelete('restrict');
            $table->foreign('component_variant_id')->references('id')->on('product.product_variants')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('product.product_units')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing.bom_items');
        Schema::dropIfExists('manufacturing.bill_of_materials');
    }
};
