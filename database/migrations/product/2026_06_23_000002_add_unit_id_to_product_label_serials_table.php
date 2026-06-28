<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.product_label_serials', function (Blueprint $table) {
            $table->uuid('unit_id')->nullable()->after('product_variant_id');
            $table->index('unit_id');
        });

        Schema::table('product.product_label_serials', function (Blueprint $table) {
            $table->foreign('unit_id')
                ->references('id')
                ->on('product.product_units')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('product.product_label_serials', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }
};
