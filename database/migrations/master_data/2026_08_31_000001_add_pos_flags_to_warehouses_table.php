<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_data.warehouses', function (Blueprint $table) {
            $table->boolean('is_pos_active')->default(false)->after('is_inventory_active');
            $table->boolean('pos_require_serial_scan')->default(true)->after('is_pos_active');
            $table->string('pos_unit_mode', 20)->default('large_only')->after('pos_require_serial_scan');
        });

        Schema::table('master_data.warehouses', function (Blueprint $table) {
            $table->index('is_pos_active');
        });

        DB::table('master_data.warehouses')
            ->where('warehouse_type_code', 'FG')
            ->where('is_inventory_active', true)
            ->whereNull('deleted_at')
            ->update([
                'is_pos_active' => true,
                'pos_require_serial_scan' => true,
                'pos_unit_mode' => 'large_only',
            ]);
    }

    public function down(): void
    {
        Schema::table('master_data.warehouses', function (Blueprint $table) {
            $table->dropIndex(['is_pos_active']);
            $table->dropColumn(['is_pos_active', 'pos_require_serial_scan', 'pos_unit_mode']);
        });
    }
};
