<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix unique constraint on product_variant_prices to include price_list_id
     * This allows multiple prices per variant based on different price lists
     */
    public function up(): void
    {
        // Drop old unique constraint (variant_id, branch_id, unit_id)
        Schema::table('product.product_variant_prices', function (Blueprint $table) {
            $table->dropUnique('product_product_variant_prices_variant_id_branch_id_unit_id_uni');
        });

        // Add new unique constraint (variant_id, branch_id, unit_id, price_list_id)
        Schema::table('product.product_variant_prices', function (Blueprint $table) {
            $table->unique(['variant_id', 'branch_id', 'unit_id', 'price_list_id'], 'product_variant_prices_unique_key');
        });
    }

    public function down(): void
    {
        // Revert back
        Schema::table('product.product_variant_prices', function (Blueprint $table) {
            $table->dropUnique('product_variant_prices_unique_key');
        });

        Schema::table('product.product_variant_prices', function (Blueprint $table) {
            $table->unique(['variant_id', 'branch_id', 'unit_id'], 'product_product_variant_prices_variant_id_branch_id_unit_id_uni');
        });
    }
};
