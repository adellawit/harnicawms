<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `remember_token` di `customer.customers`.
 *
 * Guard `customer` (portal agen) memakai model Customer sebagai Authenticatable.
 * Saat login dengan "remember me" dicentang, SessionGuard menyimpan token ke
 * kolom `remember_token`. Kolom ini belum pernah dibuat -> login gagal
 * (Undefined column remember_token).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer.customers', function (Blueprint $table) {
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('customer.customers', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
