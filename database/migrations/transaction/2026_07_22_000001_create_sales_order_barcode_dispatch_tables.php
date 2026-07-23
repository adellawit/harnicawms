<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction.sales_order_barcode_dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('sales_order_id')->unique();
            $table->string('status', 20)->default('draft');
            $table->uuid('dispatched_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'dispatched_at']);
        });

        Schema::table('transaction.sales_order_barcode_dispatches', function (Blueprint $table) {
            $table->foreign('sales_order_id')
                ->references('id')
                ->on('transaction.sales_orders')
                ->cascadeOnDelete();
            $table->foreign('dispatched_by')
                ->references('id')
                ->on('auth.users')
                ->nullOnDelete();
        });

        Schema::create('transaction.sales_order_item_serial_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('dispatch_id');
            $table->uuid('sales_order_item_id');
            $table->uuid('product_label_serial_id');
            $table->uuid('scanned_by')->nullable();
            $table->timestamp('scanned_at')->useCurrent();
            $table->timestamps();

            $table->index(['dispatch_id', 'sales_order_item_id'], 'sales_order_serial_assignments_dispatch_item_idx');
            $table->index('scanned_at');
            $table->unique('product_label_serial_id', 'sales_order_serial_assignments_serial_unique');
        });

        Schema::table('transaction.sales_order_item_serial_assignments', function (Blueprint $table) {
            $table->foreign('dispatch_id', 'sales_order_serial_assignments_dispatch_fk')
                ->references('id')
                ->on('transaction.sales_order_barcode_dispatches')
                ->cascadeOnDelete();
            $table->foreign('sales_order_item_id', 'sales_order_serial_assignments_item_fk')
                ->references('id')
                ->on('transaction.sales_order_items')
                ->cascadeOnDelete();
            $table->foreign('product_label_serial_id', 'sales_order_serial_assignments_serial_fk')
                ->references('id')
                ->on('product.product_label_serials')
                ->restrictOnDelete();
            $table->foreign('scanned_by', 'sales_order_serial_assignments_user_fk')
                ->references('id')
                ->on('auth.users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction.sales_order_item_serial_assignments');
        Schema::dropIfExists('transaction.sales_order_barcode_dispatches');
    }
};
