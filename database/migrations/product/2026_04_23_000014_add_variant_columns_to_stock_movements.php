<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add product_variant_id and product_variant_stock_id to product_stock_movements
     * to support variant-level stock tracking.
     */
    public function up(): void
    {
        Schema::table('product.product_stock_movements', function (Blueprint $table) {
            $table->uuid('product_variant_stock_id')->nullable()->after('id');
            $table->uuid('product_variant_id')->nullable()->after('product_variant_stock_id');

            $table->index('product_variant_id');
            $table->index('product_variant_stock_id');
        });

        // Populate product_variant_stock_id from existing product_stock_id where possible
        DB::statement("
            UPDATE product.product_stock_movements m
            SET product_variant_stock_id = m.product_stock_id
            WHERE EXISTS (
                SELECT 1 FROM product.product_variant_stock pvs
                WHERE pvs.id = m.product_stock_id
            )
        ");

        // Populate product_variant_id from the variant stock record
        DB::statement("
            UPDATE product.product_stock_movements m
            SET product_variant_id = pvs.product_variant_id
            FROM product.product_variant_stock pvs
            WHERE m.product_variant_stock_id = pvs.id
            AND m.product_variant_id IS NULL
        ");

        // For movements without a variant link, try to find the default variant
        DB::statement("
            UPDATE product.product_stock_movements m
            SET product_variant_id = (
                SELECT pv.id FROM product.product_variants pv
                WHERE pv.product_id = m.product_id
                AND pv.is_active = true
                AND pv.deleted_at IS NULL
                ORDER BY pv.sort_order ASC NULLS LAST, pv.created_at ASC
                LIMIT 1
            )
            WHERE m.product_variant_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('product.product_stock_movements', function (Blueprint $table) {
            $table->dropIndex(['product_variant_id']);
            $table->dropIndex(['product_variant_stock_id']);
            $table->dropColumn(['product_variant_stock_id', 'product_variant_id']);
        });
    }
};
