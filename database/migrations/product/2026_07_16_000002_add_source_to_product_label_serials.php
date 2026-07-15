<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.product_label_serials', function (Blueprint $table) {
            $table->string('source_type', 50)->nullable()->after('printed_by');
            $table->uuid('source_id')->nullable()->after('source_type');

            $table->index(['source_type', 'source_id'], 'product_label_serials_source_idx');
            $table->index(
                ['source_type', 'source_id', 'unit_level'],
                'product_label_serials_source_level_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('product.product_label_serials', function (Blueprint $table) {
            $table->dropIndex('product_label_serials_source_level_idx');
            $table->dropIndex('product_label_serials_source_idx');
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};
