<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Batch / Lot tracking - Manufacturing, F&B, Pharma
     */
    public function up(): void
    {
        Schema::create('product.product_batches', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_id');
            $table->uuid('company_id')->nullable();

            $table->string('batch_number', 100);
            $table->string('lot_number', 100)->nullable();
            $table->date('production_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'batch_number']);
            $table->index('product_id');
            $table->index('batch_number');
            $table->index('expiry_date');
        });

        Schema::table('product.product_batches', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');
        });

        // Stock per batch per branch
        Schema::create('product.product_batch_stock', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_batch_id');
            $table->uuid('branch_id');
            $table->uuid('unit_id');
            $table->decimal('quantity', 18, 6)->default(0);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->timestamps();

            $table->unique(['product_batch_id', 'branch_id']);
            $table->index('product_batch_id');
            $table->index('branch_id');
        });

        Schema::table('product.product_batch_stock', function (Blueprint $table) {
            $table->foreign('product_batch_id')
                ->references('id')
                ->on('product.product_batches')
                ->onDelete('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');

            $table->foreign('unit_id')
                ->references('id')
                ->on('product.product_units')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.product_batch_stock');
        Schema::dropIfExists('product.product_batches');
    }
};
