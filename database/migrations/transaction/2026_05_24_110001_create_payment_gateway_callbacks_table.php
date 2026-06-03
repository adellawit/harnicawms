<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction.payment_gateway_callbacks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('sales_order_id')->nullable();
            $table->uuid('sales_order_payment_id')->nullable();
            $table->string('gateway', 30)->default('xendit');
            $table->string('source', 50);
            $table->string('external_id', 100)->nullable();
            $table->string('gateway_reference', 100)->nullable();
            $table->string('invoice_status', 50)->nullable();
            $table->string('process_result', 30)->default('pending');
            $table->text('error_message')->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('sales_order_id');
            $table->index('sales_order_payment_id');
            $table->index('gateway_reference');
            $table->index('source');
            $table->index('process_result');
            $table->index('created_at');
        });

        Schema::table('transaction.payment_gateway_callbacks', function (Blueprint $table) {
            $table->foreign('sales_order_id')
                ->references('id')
                ->on('transaction.sales_orders')
                ->onDelete('set null');
            $table->foreign('sales_order_payment_id')
                ->references('id')
                ->on('transaction.sales_order_payments')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction.payment_gateway_callbacks');
    }
};
