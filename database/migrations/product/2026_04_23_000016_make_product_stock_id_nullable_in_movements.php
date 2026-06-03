<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make product_stock_id nullable so new movements can use product_variant_stock_id
     * (e.g. POS sales which reference product_variant_stock).
     */
    public function up(): void
    {
        // Drop FK if it exists (references product_stock which may have been migrated)
        try {
            DB::statement('ALTER TABLE product.product_stock_movements DROP CONSTRAINT IF EXISTS product_stock_movements_product_stock_id_foreign');
        } catch (\Throwable $e) {
            // Ignore if constraint has different name
        }

        Schema::table('product.product_stock_movements', function (Blueprint $table) {
            $table->uuid('product_stock_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product.product_stock_movements', function (Blueprint $table) {
            $table->uuid('product_stock_id')->nullable(false)->change();
        });

        // Re-add FK if product_stock table exists
        try {
            DB::statement('ALTER TABLE product.product_stock_movements ADD CONSTRAINT product_stock_movements_product_stock_id_foreign FOREIGN KEY (product_stock_id) REFERENCES product.product_stock(id) ON DELETE CASCADE');
        } catch (\Throwable $e) {
            // product_stock may not exist
        }
    }
};
