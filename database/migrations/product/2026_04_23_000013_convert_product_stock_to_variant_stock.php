<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert product_stock to product_variant_stock
     * Each variant will have its own stock
     */
    public function up(): void
    {
        // Create new table product_variant_stock
        Schema::create('product.product_variant_stock', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_variant_id');
            $table->uuid('product_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id');
            $table->uuid('unit_id');
            $table->decimal('quantity', 18, 6)->default(0);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_variant_id', 'branch_id']);
            $table->index('product_variant_id');
            $table->index('product_id');
            $table->index('company_id');
            $table->index('branch_id');
            $table->index('unit_id');
        });

        // Add foreign keys
        Schema::table('product.product_variant_stock', function (Blueprint $table) {
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product.product_variants')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');

            $table->foreign('unit_id')
                ->references('id')
                ->on('product.product_units')
                ->onDelete('restrict');
        });

        // Migrate existing data from product_stock to product_variant_stock
        // For products with variants, create stock for each variant
        // For products without variants, create a default variant first

        // First, ensure all products with is_stock_item=true have at least one variant
        DB::statement("
            INSERT INTO product.product_variants (id, product_id, sku, barcode, is_active, created_at, updated_at)
            SELECT
                public.uuid_generate_v7(),
                p.id,
                COALESCE(p.sku, p.code, 'PROD-' || substring(p.id::text, 1, 8)),
                substring(p.id::text, 1, 13),
                TRUE,
                NOW(),
                NOW()
            FROM product.products p
            LEFT JOIN product.product_variants pv ON p.id = pv.product_id
            WHERE p.is_stock_item = true
            AND pv.id IS NULL
        ");

        // Now migrate stock data
        // For each product_stock, create variant_stock for each variant of that product
        DB::statement("
            INSERT INTO product.product_variant_stock (
                id,
                product_variant_id,
                product_id,
                company_id,
                branch_id,
                unit_id,
                quantity,
                created_by,
                updated_by,
                created_at,
                updated_at
            )
            SELECT
                public.uuid_generate_v7(),
                pv.id,
                ps.product_id,
                ps.company_id,
                ps.branch_id,
                ps.unit_id,
                ps.quantity,
                ps.created_by,
                ps.updated_by,
                ps.created_at,
                ps.updated_at
            FROM product.product_stock ps
            INNER JOIN product.product_variants pv ON ps.product_id = pv.product_id
        ");
    }

    public function down(): void
    {
        // To rollback, we would need to aggregate variant_stock back to product_stock
        // This is a complex operation, so we'll just drop the new table
        Schema::dropIfExists('product.product_variant_stock');
    }
};
