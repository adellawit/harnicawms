<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dedicated address columns:
     * - address_ktp: identity / KTP address
     * - address_shipping: delivery / domicile address
     * Existing `address` is kept for backward compatibility (synced with shipping).
     */
    public function up(): void
    {
        Schema::table('customer.customers', function (Blueprint $table) {
            $table->text('address_ktp')->nullable()->after('mobile');
            $table->text('address_shipping')->nullable()->after('address_ktp');
        });

        // Backfill shipping from legacy address
        DB::statement('
            UPDATE customer.customers
            SET address_shipping = address
            WHERE address_shipping IS NULL
              AND address IS NOT NULL
              AND address <> \'\'
        ');

        // Backfill KTP from notes lines "Alamat KTP: ..."
        DB::statement("
            UPDATE customer.customers
            SET address_ktp = NULLIF(TRIM(SUBSTRING(notes FROM 'Alamat KTP: ([^\n]+)')), '')
            WHERE address_ktp IS NULL
              AND notes ~ 'Alamat KTP:'
        ");
    }

    public function down(): void
    {
        Schema::table('customer.customers', function (Blueprint $table) {
            $table->dropColumn(['address_ktp', 'address_shipping']);
        });
    }
};
