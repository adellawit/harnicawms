<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * COD tidak dipakai di POS (sama efeknya dengan tunai langsung).
     * Nonaktifkan agar tidak muncul di daftar pembayaran.
     */
    public function up(): void
    {
        DB::table('master_data.method_payments')
            ->where('code', 'COD')
            ->whereNull('deleted_at')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('master_data.method_payments')
            ->where('code', 'COD')
            ->whereNull('deleted_at')
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
