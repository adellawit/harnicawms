<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Product stock movements - audit trail for inventory changes
     * reference_type + reference_id: polymorphic (PurchaseOrder, SaleOrder, ManufacturingOrder, StockAdjustment, etc)
     */
    public function up(): void
    {
        Schema::create('product.product_stock_movements', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_stock_id');
            $table->uuid('product_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id');
            $table->uuid('unit_id');
            $table->uuid('stock_mutation_type_id')->nullable();

            $table->string('type', 20); // in, out, adjustment
            $table->decimal('quantity', 18, 6);
            $table->decimal('quantity_before', 18, 6);
            $table->decimal('quantity_after', 18, 6);

            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('product_stock_id');
            $table->index('product_id');
            $table->index('company_id');
            $table->index('branch_id');
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['product_id', 'created_at']);
        });

        Schema::table('product.product_stock_movements', function (Blueprint $table) {
            $table->foreign('product_stock_id')
                ->references('id')
                ->on('product.product_stock')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');

            $table->foreign('unit_id')
                ->references('id')
                ->on('product.product_units')
                ->onDelete('restrict');

            $table->foreign('stock_mutation_type_id')
                ->references('id')
                ->on('public.stock_mutation_types')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.product_stock_movements');
    }
};
