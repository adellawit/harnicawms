<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturing.bom_items', function (Blueprint $table) {
            $table->decimal('last_unit_cost', 18, 4)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('manufacturing.bom_items', function (Blueprint $table) {
            $table->dropColumn('last_unit_cost');
        });
    }
};
