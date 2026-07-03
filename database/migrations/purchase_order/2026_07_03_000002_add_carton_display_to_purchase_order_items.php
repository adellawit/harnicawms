<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('product.purchase_order_items', 'carton_display')) {
                $table->string('carton_display', 500)->nullable()->after('carton_qty');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product.purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('product.purchase_order_items', 'carton_display')) {
                $table->dropColumn('carton_display');
            }
        });
    }
};
