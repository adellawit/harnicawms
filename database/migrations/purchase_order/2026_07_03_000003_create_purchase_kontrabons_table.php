<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product.purchase_kontrabons', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->string('kontrabon_number', 50)->unique();
            $table->date('kontrabon_date');
            $table->uuid('supplier_id')->nullable();
            $table->string('supplier_name', 200)->nullable();
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            $table->date('payment_date')->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->string('payment_method', 80)->nullable();
            $table->text('payment_notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('kontrabon_date');
            $table->index('status');
            $table->index('supplier_id');
            $table->index('branch_id');
        });

        Schema::table('product.purchase_kontrabons', function (Blueprint $table) {
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

        Schema::create('product.purchase_kontrabon_items', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('kontrabon_id');
            $table->uuid('purchase_order_id');
            $table->string('supplier_invoice_number', 80)->nullable();
            $table->date('supplier_invoice_date')->nullable();
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('other_cost_amount', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('kontrabon_id');
            $table->index('purchase_order_id');
        });

        Schema::table('product.purchase_kontrabon_items', function (Blueprint $table) {
            $table->foreign('kontrabon_id')
                ->references('id')
                ->on('product.purchase_kontrabons')
                ->onDelete('cascade');

            $table->foreign('purchase_order_id')
                ->references('id')
                ->on('product.purchase_orders')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.purchase_kontrabon_items');
        Schema::dropIfExists('product.purchase_kontrabons');
    }
};
