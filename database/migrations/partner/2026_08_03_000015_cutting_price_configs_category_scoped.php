<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS partner.cutting_price_configs_product_unit_unique');

        Schema::table('partner.cutting_price_configs', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('partner.cutting_price_configs', function (Blueprint $table) {
            $table->uuid('product_id')->nullable()->change();
        });

        // Config applies to whole category; clear product binding.
        DB::table('partner.cutting_price_configs')->update(['product_id' => null]);

        DB::statement('
            CREATE UNIQUE INDEX cutting_price_configs_category_unit_unique
            ON partner.cutting_price_configs (category_id, unit_code)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS partner.cutting_price_configs_category_unit_unique');

        Schema::table('partner.cutting_price_configs', function (Blueprint $table) {
            $table->uuid('product_id')->nullable(false)->change();
        });

        Schema::table('partner.cutting_price_configs', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('restrict');
        });

        DB::statement('
            CREATE UNIQUE INDEX cutting_price_configs_product_unit_unique
            ON partner.cutting_price_configs (product_id, unit_code)
            WHERE deleted_at IS NULL
        ');
    }
};
