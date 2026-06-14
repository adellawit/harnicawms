<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Production Orders - perintah produksi yang mengkonsumsi bahan baku (FIFO)
     * dan menghasilkan produk jadi dengan HPP terhitung.
     */
    public function up(): void
    {
        Schema::create('manufacturing.production_orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->string('order_number', 50)->unique();
            $table->date('production_date');
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id');                  // lokasi produksi (gudang distributor)
            $table->uuid('bom_id')->nullable();
            $table->uuid('product_id');                 // produk jadi
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('output_unit_id');

            $table->decimal('planned_qty', 18, 6)->default(0);
            $table->decimal('produced_qty', 18, 6)->default(0);
            $table->decimal('total_material_cost', 18, 4)->default(0);
            $table->decimal('overhead_cost', 18, 4)->default(0);
            $table->decimal('output_unit_cost', 18, 4)->default(0); // HPP per unit produk jadi

            $table->string('status', 20)->default('draft'); // draft, in_progress, completed, cancelled
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('branch_id');
            $table->index('product_id');
            $table->index('status');
            $table->index('production_date');
        });

        Schema::table('manufacturing.production_orders', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('master_data.business_units')->onDelete('cascade');
            $table->foreign('bom_id')->references('id')->on('manufacturing.bill_of_materials')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('product.products')->onDelete('restrict');
            $table->foreign('product_variant_id')->references('id')->on('product.product_variants')->onDelete('set null');
            $table->foreign('output_unit_id')->references('id')->on('product.product_units')->onDelete('restrict');
        });

        // === Bahan baku terkonsumsi ===
        Schema::create('manufacturing.production_order_materials', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('production_order_id');
            $table->uuid('component_product_id');
            $table->uuid('component_variant_id')->nullable();
            $table->uuid('unit_id');
            $table->decimal('qty_consumed', 18, 6)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);  // HPP rata-rata FIFO saat konsumsi
            $table->decimal('total_cost', 18, 4)->default(0);

            $table->timestamps();

            $table->index('production_order_id');
            $table->index('component_variant_id');
        });

        Schema::table('manufacturing.production_order_materials', function (Blueprint $table) {
            $table->foreign('production_order_id')->references('id')->on('manufacturing.production_orders')->onDelete('cascade');
            $table->foreign('component_product_id')->references('id')->on('product.products')->onDelete('restrict');
            $table->foreign('component_variant_id')->references('id')->on('product.product_variants')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('product.product_units')->onDelete('restrict');
        });

        // === Hasil produksi (produk jadi) ===
        Schema::create('manufacturing.production_order_outputs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('production_order_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('unit_id');
            $table->decimal('qty_produced', 18, 6)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);  // HPP per unit
            $table->decimal('total_cost', 18, 4)->default(0);

            $table->timestamps();

            $table->index('production_order_id');
            $table->index('product_variant_id');
        });

        Schema::table('manufacturing.production_order_outputs', function (Blueprint $table) {
            $table->foreign('production_order_id')->references('id')->on('manufacturing.production_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('product.products')->onDelete('restrict');
            $table->foreign('product_variant_id')->references('id')->on('product.product_variants')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('product.product_units')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing.production_order_outputs');
        Schema::dropIfExists('manufacturing.production_order_materials');
        Schema::dropIfExists('manufacturing.production_orders');
    }
};
