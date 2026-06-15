<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FIFO cost layers - each inbound creates a layer (qty_remaining, unit_cost).
     * Outbound consumes oldest layers first. Scoped per variant + branch.
     */
    public function up(): void
    {
        Schema::create('product.product_cost_layers', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id');
            $table->uuid('unit_id');

            $table->decimal('quantity', 18, 6);            // original qty of the layer
            $table->decimal('quantity_remaining', 18, 6);  // remaining to consume (FIFO)
            $table->decimal('unit_cost', 18, 4);           // cost per unit for this layer

            $table->string('source_type', 50)->nullable(); // PurchaseReceive, Production, ReplenishmentReceipt, ReturnIn, Inbound
            $table->uuid('source_id')->nullable();
            $table->date('effective_date');
            $table->date('expiry_date')->nullable(); // untuk FEFO (produk herbal)

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('branch_id');
            $table->index('effective_date');
            $table->index('expiry_date');
            // Hot path for FEFO/FIFO consumption (earliest expiry / oldest remaining first)
            $table->index(['product_variant_id', 'branch_id', 'quantity_remaining']);
        });

        Schema::table('product.product_cost_layers', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')->on('product.products')->onDelete('cascade');

            $table->foreign('product_variant_id')
                ->references('id')->on('product.product_variants')->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')->on('master_data.business_units')->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')->on('master_data.business_units')->onDelete('cascade');

            $table->foreign('unit_id')
                ->references('id')->on('product.product_units')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.product_cost_layers');
    }
};
