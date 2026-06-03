<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product.product_variants', function (Blueprint $table) {
            $table->renameColumn('price_adjustment', 'purchase_price');
            $table->renameColumn('cost_adjustment', 'selling_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product.product_variants', function (Blueprint $table) {
            $table->renameColumn('purchase_price', 'price_adjustment');
            $table->renameColumn('selling_price', 'cost_adjustment');
        });
    }
};
