<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product.purchase_order_receives', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('purchase_order_id');
            $table->string('receive_number', 50)->unique();
            $table->date('receive_date');
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('purchase_order_id');
            $table->index('receive_date');
        });

        Schema::table('product.purchase_order_receives', function (Blueprint $table) {
            $table->foreign('purchase_order_id')
                ->references('id')
                ->on('product.purchase_orders')
                ->onDelete('cascade');
        });

        Schema::create('product.purchase_order_receive_items', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('receive_id');
            $table->uuid('purchase_order_item_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->uuid('unit_id');

            $table->decimal('quantity_received', 18, 6);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->timestamps();

            $table->index('receive_id');
            $table->index('purchase_order_item_id');
            $table->index('product_id');
        });

        Schema::table('product.purchase_order_receive_items', function (Blueprint $table) {
            $table->foreign('receive_id')
                ->references('id')
                ->on('product.purchase_order_receives')
                ->onDelete('cascade');

            $table->foreign('purchase_order_item_id')
                ->references('id')
                ->on('product.purchase_order_items')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('restrict');

            $table->foreign('variant_id')
                ->references('id')
                ->on('product.product_variants')
                ->onDelete('set null');

            $table->foreign('unit_id')
                ->references('id')
                ->on('product.product_units')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.purchase_order_receive_items');
        Schema::dropIfExists('product.purchase_order_receives');
    }
};
