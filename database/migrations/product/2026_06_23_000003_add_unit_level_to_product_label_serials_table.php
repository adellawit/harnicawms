<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.product_label_serials', function (Blueprint $table) {
            $table->unsignedTinyInteger('unit_level')->nullable()->after('unit_id');
            $table->index(['product_id', 'unit_id', 'year_prefix', 'sequence'], 'product_label_serials_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product.product_label_serials', function (Blueprint $table) {
            $table->dropIndex('product_label_serials_scope_idx');
            $table->dropColumn('unit_level');
        });
    }
};
