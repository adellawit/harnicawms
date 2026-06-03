<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Product Price Lists - support multiple price levels/channels
     * Examples: Regular, Toko, GoFood, GrabFood, ShopeeFood, Dine-in, Delivery, etc
     */
    public function up(): void
    {
        Schema::create('product.product_price_lists', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();

            $table->string('channel_type', 30)->nullable(); // pos, marketplace, delivery, etc
            $table->string('external_channel_code', 50)->nullable(); // gofood, grabfood, shopeefood, etc

            $table->integer('sort_order')->default(0);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('company_id');
            $table->index('branch_id');
            $table->index('is_active');
            $table->index('sort_order');
        });

        Schema::table('product.product_price_lists', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');
        });

        // Add price_list_id to product_prices
        Schema::table('product.product_prices', function (Blueprint $table) {
            $table->uuid('price_list_id')->nullable()->after('unit_id');

            $table->foreign('price_list_id')
                ->references('id')
                ->on('product.product_price_lists')
                ->onDelete('set null');

            $table->index('price_list_id');
        });

        // Add price_list_id to product_variant_prices
        Schema::table('product.product_variant_prices', function (Blueprint $table) {
            $table->uuid('price_list_id')->nullable()->after('unit_id');

            $table->foreign('price_list_id')
                ->references('id')
                ->on('product.product_price_lists')
                ->onDelete('set null');

            $table->index('price_list_id');
        });
    }

    public function down(): void
    {
        Schema::table('product.product_variant_prices', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropIndex(['price_list_id']);
            $table->dropColumn('price_list_id');
        });

        Schema::table('product.product_prices', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropIndex(['price_list_id']);
            $table->dropColumn('price_list_id');
        });

        Schema::dropIfExists('product.product_price_lists');
    }
};
