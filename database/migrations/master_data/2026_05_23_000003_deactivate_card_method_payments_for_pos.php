<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kartu debit/kredit di POS memicu alur tunai (langsung lunas).
     * Nonaktifkan di kasir; tetap bisa dipakai modul lain jika diaktifkan lagi.
     */
    public function up(): void
    {
        DB::table('master_data.method_payments')
            ->whereIn('code', ['DEBIT', 'CREDIT'])
            ->whereNull('deleted_at')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('master_data.method_payments')
            ->whereIn('code', ['DEBIT', 'CREDIT'])
            ->whereNull('deleted_at')
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
