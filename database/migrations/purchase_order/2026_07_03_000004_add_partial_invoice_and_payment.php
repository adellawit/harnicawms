<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.purchase_kontrabons', function (Blueprint $table) {
            $table->decimal('paid_amount', 18, 4)->default(0)->after('total');
        });

        Schema::table('product.purchase_kontrabon_items', function (Blueprint $table) {
            $table->decimal('po_total', 18, 4)->default(0)->after('purchase_order_id');
        });

        Schema::create('product.purchase_kontrabon_payments', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('kontrabon_id');
            $table->date('payment_date');
            $table->decimal('amount', 18, 4);
            $table->string('payment_reference', 120)->nullable();
            $table->string('payment_method', 80)->nullable();
            $table->text('payment_notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('kontrabon_id');
            $table->index('payment_date');
        });

        Schema::table('product.purchase_kontrabon_payments', function (Blueprint $table) {
            $table->foreign('kontrabon_id')
                ->references('id')
                ->on('product.purchase_kontrabons')
                ->onDelete('cascade');
        });

        DB::table('product.purchase_kontrabon_items')->update([
            'po_total' => DB::raw('total'),
        ]);

        DB::table('product.purchase_kontrabons')
            ->where('status', 'paid')
            ->update(['paid_amount' => DB::raw('total')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product.purchase_kontrabon_payments');

        Schema::table('product.purchase_kontrabon_items', function (Blueprint $table) {
            $table->dropColumn('po_total');
        });

        Schema::table('product.purchase_kontrabons', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
