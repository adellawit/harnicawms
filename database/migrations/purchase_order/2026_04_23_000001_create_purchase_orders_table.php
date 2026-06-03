<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Purchase Orders - unified (supports raw materials & finished goods)
     */
    public function up(): void
    {
        Schema::create('product.purchase_orders', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->string('purchase_number', 50)->unique();
            $table->date('purchase_date');
            $table->uuid('supplier_id')->nullable();
            $table->string('supplier_name', 200)->nullable();
            $table->string('supplier_contact', 100)->nullable();
            $table->text('supplier_address')->nullable();

            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();

            $table->string('status', 30)->default('draft');
            $table->date('expected_delivery_date')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('purchase_date');
            $table->index('status');
            $table->index('supplier_id');
            $table->index('company_id');
            $table->index('branch_id');
        });

        Schema::table('product.purchase_orders', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('master_data.suppliers')
                ->onDelete('set null');

            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');
        });

        Schema::create('product.purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('purchase_order_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->uuid('unit_id');

            $table->decimal('quantity', 18, 6);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('purchase_order_id');
            $table->index('product_id');
            $table->index('variant_id');
            $table->index('unit_id');
        });

        Schema::table('product.purchase_order_items', function (Blueprint $table) {
            $table->foreign('purchase_order_id')
                ->references('id')
                ->on('product.purchase_orders')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('restrict');

            $table->foreign('unit_id')
                ->references('id')
                ->on('product.product_units')
                ->onDelete('restrict');

            $table->foreign('variant_id')
                ->references('id')
                ->on('product.product_variants')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.purchase_order_items');
        Schema::dropIfExists('product.purchase_orders');
    }
};
