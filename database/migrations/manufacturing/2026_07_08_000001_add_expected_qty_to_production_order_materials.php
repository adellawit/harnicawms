<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturing.production_order_materials', function (Blueprint $table) {
            $table->decimal('expected_qty', 18, 6)->nullable()->after('qty_consumed');
        });
    }

    public function down(): void
    {
        Schema::table('manufacturing.production_order_materials', function (Blueprint $table) {
            $table->dropColumn('expected_qty');
        });
    }
};
