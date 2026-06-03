<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Product prices per branch & unit (purchase & selling)
     */
    public function up(): void
    {
        Schema::create('product.product_prices', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id');
            $table->uuid('unit_id');

            $table->decimal('purchase_price', 18, 4)->default(0);
            $table->decimal('selling_price', 18, 4)->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'branch_id', 'unit_id']);
            $table->index('company_id');
            $table->index('branch_id');
            $table->index('unit_id');
        });

        Schema::table('product.product_prices', function (Blueprint $table) {
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.product_prices');
    }
};
