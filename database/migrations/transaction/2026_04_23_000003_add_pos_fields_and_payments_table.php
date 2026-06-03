<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add POS-specific columns to sales_orders
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            $table->uuid('price_list_id')->nullable()->after('warehouse_id');
            $table->uuid('method_payment_id')->nullable()->after('price_list_id');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('subtotal');
            $table->boolean('tax_enabled')->default(true)->after('tax_rate');
            $table->string('discount_type', 10)->default('percent')->after('discount_amount');
            $table->decimal('discount_value', 18, 4)->default(0)->after('discount_type');
            $table->decimal('item_discount_total', 18, 4)->default(0)->after('discount_value');
            $table->string('payment_status', 20)->default('unpaid')->after('status');
            $table->timestamp('paid_at')->nullable()->after('fulfilled_at');
        });

        // Add item-level discount fields to sales_order_items
        Schema::table('transaction.sales_order_items', function (Blueprint $table) {
            $table->string('discount_type', 10)->default('percent')->after('discount_amount');
            $table->decimal('discount_value', 18, 4)->default(0)->after('discount_type');
        });

        // Payments table
        Schema::create('transaction.sales_order_payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('sales_order_id');
            $table->uuid('method_payment_id')->nullable();
            $table->string('payment_code', 50)->nullable();
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('change_amount', 18, 4)->default(0);
            $table->string('status', 20)->default('completed');
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sales_order_id');
            $table->index('method_payment_id');
            $table->index('status');
        });

        Schema::table('transaction.sales_order_payments', function (Blueprint $table) {
            $table->foreign('sales_order_id')->references('id')->on('transaction.sales_orders')->onDelete('cascade');
            $table->foreign('method_payment_id')->references('id')->on('master_data.method_payments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction.sales_order_payments');

        Schema::table('transaction.sales_order_items', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value']);
        });

        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            $table->dropColumn([
                'price_list_id', 'method_payment_id', 'tax_rate', 'tax_enabled',
                'discount_type', 'discount_value', 'item_discount_total',
                'payment_status', 'paid_at',
            ]);
        });
    }
};
