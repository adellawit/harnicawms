<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * UUID v7 is a time-based UUID that provides better database indexing
     * performance due to its monotonic nature.
     *
     * Format: XXXXXXXX-XXXX-7XXX-XXXX-XXXXXXXXXXXX
     * - First 32 bits: timestamp_ms >> 16
     * - Next 16 bits: (timestamp_ms & 0xFFFF) with version in high nibble
     * - Next 16 bits: variant (10xx) + random
     * - Last 64 bits: random
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9562
     */
    public function up(): void
    {
        // Create UUID v7 function for PostgreSQL (compatible with older versions)
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');

        DB::statement('
            CREATE OR REPLACE FUNCTION public.uuid_generate_v7()
            RETURNS uuid
            LANGUAGE sql
            VOLATILE
            PARALLEL SAFE
            AS $function$
            SELECT (
                -- First 8 hex chars: high 32 bits of timestamp
                substring(timestamp_hex, 1, 8) ||
                \'-\' ||
                -- Next 4 hex chars: low 16 bits of timestamp (with version = 7)
                \'7\' || substring(timestamp_hex, 9, 3) ||
                \'-\' ||
                -- Next 4 hex chars: variant (8xxx = 10xx) + random
                \'8\' || lpad(to_hex((random() * 4095)::int), 3, \'0\') ||
                \'-\' ||
                -- Next 4 hex chars: random
                lpad(to_hex((random() * 65535)::int), 4, \'0\') ||
                \'-\' ||
                -- Last 12 hex chars: random
                lpad(to_hex((random() * 281474976710656)::bigint), 12, \'0\')
            )::uuid
            FROM (
                SELECT lpad(to_hex((EXTRACT(EPOCH FROM clock_timestamp()) * 1000)::bigint), 12, \'0\') AS timestamp_hex
            ) AS t;
            $function$;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS public.uuid_generate_v7()');
    }
};
