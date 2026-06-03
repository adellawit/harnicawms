<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_data.suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('master_data.suppliers', 'tax_id')) {
                $table->dropColumn('tax_id');
            }
        });
        Schema::table('master_data.suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('master_data.suppliers', 'is_ppn')) {
                $table->boolean('is_ppn')->default(false)->after('email')->comment('PPN / NON PPN');
            }
            if (! Schema::hasColumn('master_data.suppliers', 'ppn_rate')) {
                $table->decimal('ppn_rate', 5, 2)->nullable()->after('is_ppn')->comment('PPN % jika PPN');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_data.suppliers', function (Blueprint $table) {
            $table->dropColumn(['is_ppn', 'ppn_rate']);
        });
    }
};
