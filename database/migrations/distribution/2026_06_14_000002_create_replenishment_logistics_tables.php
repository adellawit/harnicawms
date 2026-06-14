<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Logistik replenishment: Pengiriman (shipment), Penerimaan (receipt), Retur (return).
     */
    public function up(): void
    {
        // === Pengiriman (Distributor -> Agen) ===
        Schema::create('distribution.shipments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->string('shipment_number', 50)->unique();
            $table->uuid('order_id');
            $table->date('ship_date');
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('status', 20)->default('in_transit'); // preparing, in_transit, delivered
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
            $table->index('status');
        });

        Schema::table('distribution.shipments', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('distribution.replenishment_orders')->onDelete('cascade');
        });

        Schema::create('distribution.shipment_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('shipment_id');
            $table->uuid('order_item_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('unit_id');
            $table->decimal('quantity', 18, 6)->default(0);

            $table->timestamps();

            $table->index('shipment_id');
            $table->index('order_item_id');
        });

        Schema::table('distribution.shipment_items', function (Blueprint $table) {
            $table->foreign('shipment_id')->references('id')->on('distribution.shipments')->onDelete('cascade');
            $table->foreign('order_item_id')->references('id')->on('distribution.replenishment_order_items')->onDelete('cascade');
        });

        // === Penerimaan (di Agen) ===
        Schema::create('distribution.receipts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->string('receipt_number', 50)->unique();
            $table->uuid('order_id');
            $table->uuid('shipment_id')->nullable();
            $table->date('receive_date');
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
            $table->index('shipment_id');
        });

        Schema::table('distribution.receipts', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('distribution.replenishment_orders')->onDelete('cascade');
            $table->foreign('shipment_id')->references('id')->on('distribution.shipments')->onDelete('set null');
        });

        Schema::create('distribution.receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('receipt_id');
            $table->uuid('order_item_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('unit_id');
            $table->decimal('quantity', 18, 6)->default(0);

            $table->timestamps();

            $table->index('receipt_id');
            $table->index('order_item_id');
        });

        Schema::table('distribution.receipt_items', function (Blueprint $table) {
            $table->foreign('receipt_id')->references('id')->on('distribution.receipts')->onDelete('cascade');
            $table->foreign('order_item_id')->references('id')->on('distribution.replenishment_order_items')->onDelete('cascade');
        });

        // === Retur (Agen -> Distributor) ===
        Schema::create('distribution.returns', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->string('return_number', 50)->unique();
            $table->uuid('order_id');
            $table->uuid('agent_id');
            $table->uuid('distributor_id');
            $table->date('return_date');
            $table->string('reason', 255)->nullable();
            $table->string('status', 20)->default('completed'); // draft, completed, cancelled
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
            $table->index('agent_id');
        });

        Schema::table('distribution.returns', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('distribution.replenishment_orders')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('master_data.business_units')->onDelete('cascade');
            $table->foreign('distributor_id')->references('id')->on('master_data.business_units')->onDelete('cascade');
        });

        Schema::create('distribution.return_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('return_id');
            $table->uuid('order_item_id')->nullable();
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('unit_id');
            $table->decimal('quantity', 18, 6)->default(0);

            $table->timestamps();

            $table->index('return_id');
        });

        Schema::table('distribution.return_items', function (Blueprint $table) {
            $table->foreign('return_id')->references('id')->on('distribution.returns')->onDelete('cascade');
            $table->foreign('order_item_id')->references('id')->on('distribution.replenishment_order_items')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution.return_items');
        Schema::dropIfExists('distribution.returns');
        Schema::dropIfExists('distribution.receipt_items');
        Schema::dropIfExists('distribution.receipts');
        Schema::dropIfExists('distribution.shipment_items');
        Schema::dropIfExists('distribution.shipments');
    }
};
