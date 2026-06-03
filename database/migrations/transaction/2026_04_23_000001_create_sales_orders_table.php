<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sales orders - universal (Retail POS, F&B, B2B, Distributor)
     */
    public function up(): void
    {
        Schema::create('product.sales_orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->string('sales_number', 50)->unique();
            $table->date('sales_date');
            $table->string('order_type', 30)->default('standard');
            $table->uuid('customer_id')->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->string('customer_contact', 100)->nullable();
            $table->text('customer_address')->nullable();
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id');
            $table->uuid('warehouse_id')->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('expected_delivery_date')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('shipping_amount', 18, 4)->default(0)->nullable();
            $table->decimal('total', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->string('reference', 100)->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('sales_date');
            $table->index('status');
            $table->index('order_type');
            $table->index('customer_id');
            $table->index('company_id');
            $table->index('branch_id');
            $table->index('warehouse_id');
        });

        Schema::table('product.sales_orders', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('master_data.business_units')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('master_data.business_units')->onDelete('set null');
        });

        Schema::create('product.sales_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('sales_order_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
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
            $table->index('sales_order_id');
            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('unit_id');
        });

        Schema::table('product.sales_order_items', function (Blueprint $table) {
            $table->foreign('sales_order_id')->references('id')->on('product.sales_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('product.products')->onDelete('restrict');
            $table->foreign('product_variant_id')->references('id')->on('product.product_variants')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('product.product_units')->onDelete('restrict');
        });

        Schema::create('product.sales_order_item_modifiers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('sales_order_item_id');
            $table->uuid('modifier_option_id');
            $table->decimal('quantity', 18, 6)->default(1);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->timestamps();
            $table->index('sales_order_item_id');
            $table->index('modifier_option_id');
        });

        Schema::table('product.sales_order_item_modifiers', function (Blueprint $table) {
            $table->foreign('sales_order_item_id')->references('id')->on('product.sales_order_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.sales_order_item_modifiers');
        Schema::dropIfExists('product.sales_order_items');
        Schema::dropIfExists('product.sales_orders');
    }
};
