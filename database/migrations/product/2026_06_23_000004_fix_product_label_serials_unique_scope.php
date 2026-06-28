<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.product_label_serials', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
            $table->unique(['product_id', 'serial_number'], 'product_label_serials_product_serial_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product.product_label_serials', function (Blueprint $table) {
            $table->dropUnique('product_label_serials_product_serial_unique');
            $table->unique('serial_number');
        });
    }
};
