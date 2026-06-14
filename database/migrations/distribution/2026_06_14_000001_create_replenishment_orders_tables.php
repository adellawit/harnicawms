<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Distribution schema - Replenishment Order (Agen pesan ke Distributor).
     */
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS distribution');

        Schema::create('distribution.replenishment_orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->string('order_number', 50)->unique();
            $table->date('order_date');
            $table->uuid('distributor_id');             // company (penjual)
            $table->uuid('agent_id');                   // branch (pembeli)
            $table->uuid('price_list_id')->nullable();

            // draft, submitted, approved, shipped, partially_received, received, cancelled
            $table->string('status', 30)->default('draft');

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('distributor_id');
            $table->index('agent_id');
            $table->index('status');
            $table->index('order_date');
        });

        Schema::table('distribution.replenishment_orders', function (Blueprint $table) {
            $table->foreign('distributor_id')->references('id')->on('master_data.business_units')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('master_data.business_units')->onDelete('cascade');
        });

        Schema::create('distribution.replenishment_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('unit_id');
            $table->decimal('qty_ordered', 18, 6)->default(0);
            $table->decimal('qty_shipped', 18, 6)->default(0);
            $table->decimal('qty_received', 18, 6)->default(0);
            $table->decimal('qty_returned', 18, 6)->default(0);
            $table->decimal('unit_price', 18, 4)->default(0);  // harga transfer distributor -> agen
            $table->decimal('subtotal', 18, 4)->default(0);

            $table->timestamps();

            $table->index('order_id');
            $table->index('product_variant_id');
        });

        Schema::table('distribution.replenishment_order_items', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('distribution.replenishment_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('product.products')->onDelete('restrict');
            $table->foreign('product_variant_id')->references('id')->on('product.product_variants')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('product.product_units')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution.replenishment_order_items');
        Schema::dropIfExists('distribution.replenishment_orders');
    }
};
