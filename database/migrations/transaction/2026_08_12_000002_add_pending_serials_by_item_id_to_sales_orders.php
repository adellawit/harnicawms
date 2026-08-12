<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `pending_serials_by_item_id` (jsonb) di `transaction.sales_orders`.
 *
 * PosCheckoutService::createSalesOrder() menyimpan map [item_id => serial[]] pada
 * kolom ini, dan dibaca kembali saat alokasi serial. Pada POS agen alurnya lintas
 * request ("Order" lalu bayar via payPendingOrder), sehingga map ini WAJIB persist
 * di DB. Kolom sebelumnya tak pernah dibuat migrasinya -> insert/update gagal
 * (Undefined column pending_serials_by_item_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            $table->jsonb('pending_serials_by_item_id')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('transaction.sales_orders', function (Blueprint $table) {
            $table->dropColumn('pending_serials_by_item_id');
        });
    }
};
