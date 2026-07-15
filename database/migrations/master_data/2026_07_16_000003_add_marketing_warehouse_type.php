<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('master_data.warehouse_types')->updateOrInsert(
            ['code' => 'MARKETING'],
            [
                'name' => 'Gudang Marketing',
                'description' => 'Stok untuk keperluan marketing / sampel / kampanye',
                'sort_order' => 45,
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        DB::table('master_data.warehouse_types')->where('code', 'MARKETING')->delete();
    }
};
