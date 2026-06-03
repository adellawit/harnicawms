<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Customer groups - segmentasi pelanggan per cabang dengan aturan bisnis:
     * - price_level (via price_list_id)
     * - default_discount, allow_credit, credit_limit, payment_term_days
     * - earn_point, point_multiplier (loyalty)
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE SCHEMA IF NOT EXISTS customer');

        Schema::create('customer.customer_groups', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('branch_id');
            $table->string('code', 50)->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();

            // === BISNIS: Harga & Diskon ===
            $table->uuid('price_list_id')->nullable();
            $table->decimal('default_discount', 8, 2)->default(0);

            // === BISNIS: Kredit & Pembayaran ===
            $table->boolean('allow_credit')->default(false);
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->integer('payment_term_days')->default(0);

            // === BISNIS: Loyalty / Poin ===
            $table->boolean('earn_point')->default(false);
            $table->decimal('point_multiplier', 5, 2)->default(1);

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('branch_id');
            $table->index('price_list_id');
            $table->index('is_active');
            $table->index('sort_order');
        });

        Schema::table('customer.customer_groups', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');

            $table->foreign('price_list_id')
                ->references('id')
                ->on('product.product_price_lists')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer.customer_groups');
    }
};
