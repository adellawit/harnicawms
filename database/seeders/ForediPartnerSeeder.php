<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Services\Partner\ForediPartnerImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Foredi/Harnica Agent & Reseller from docs Excel.
 *
 * Writes dedicated columns (not notes):
 * - customers.address_ktp
 * - customers.address_shipping (+ legacy customers.address synced)
 * - lat/long from Alamat Domisili via Nominatim (unless FOREDI_SEED_SKIP_GEOCODE=1)
 *
 * Depends on: BusinessUnitSeeder (SUHARA-001 + SUHARA-BDG-001)
 *             and migration customer/..._add_address_ktp_and_shipping_to_customers
 */
class ForediPartnerSeeder extends Seeder
{
    public function run(ForediPartnerImportService $importer): void
    {
        $file = base_path('docs/Daftar_Agent_dan_Reseller_Foredi.xlsx');
        if (! is_file($file)) {
            $this->command?->warn("Foredi Excel not found, skip: {$file}");

            return;
        }

        $withGeocode = ! in_array(strtolower((string) env('FOREDI_SEED_SKIP_GEOCODE', '')), ['1', 'true', 'yes'], true);

        $this->command?->info('Seeding Foredi partners (address_ktp + address_shipping columns)...');
        if ($withGeocode) {
            $this->command?->warn('Geocoding is rate-limited (~1 req/s); this may take ~1 minute.');
        } else {
            $this->command?->info('Geocode skipped (FOREDI_SEED_SKIP_GEOCODE=1).');
        }

        try {
            $stats = $importer->import(
                filePath: $file,
                companyCode: 'SUHARA-001',
                branchCode: 'SUHARA-BDG-001',
                withGeocode: $withGeocode,
                onProgress: fn (string $message) => $this->command?->line('  ' . $message),
            );
        } catch (\Throwable $e) {
            $this->command?->error('ForediPartnerSeeder failed: ' . $e->getMessage());

            throw $e;
        }

        $this->sanitizeImportedNotesAndSyncPartnerAddresses();

        $filled = Customer::query()
            ->whereIn('customer_type', [Customer::TYPE_AGENT, Customer::TYPE_RESELLER])
            ->whereNotNull('address_ktp')
            ->where('address_ktp', '<>', '')
            ->whereNotNull('address_shipping')
            ->where('address_shipping', '<>', '')
            ->count();

        $this->command?->info(sprintf(
            'Foredi partners seeded: agents=%d(+%d upd), resellers=%d(+%d upd), geocoded=%d, failed=%d, address_cols_filled=%d',
            $stats['agents_created'],
            $stats['agents_updated'],
            $stats['resellers_created'],
            $stats['resellers_updated'],
            $stats['geocoded'],
            $stats['geocode_failed'],
            $filled
        ));
    }

    /**
     * Strip legacy "Alamat KTP:" lines from notes and keep partner address = shipping.
     */
    private function sanitizeImportedNotesAndSyncPartnerAddresses(): void
    {
        $customers = Customer::query()
            ->whereIn('customer_type', [Customer::TYPE_AGENT, Customer::TYPE_RESELLER])
            ->where('notes', 'like', '%Imported from Daftar_Agent_dan_Reseller_Foredi.xlsx%')
            ->get();

        foreach ($customers as $customer) {
            $notes = (string) $customer->notes;
            $cleaned = preg_replace('/^Alamat KTP:.*(?:\r\n|\n|\r)?/mi', '', $notes) ?? $notes;
            $cleaned = trim(preg_replace("/\n{3,}/", "\n\n", $cleaned) ?? $cleaned);

            $shipping = $customer->address_shipping ?: $customer->address;
            $ktp = $customer->address_ktp ?: $shipping;

            $customer->forceFill([
                'address_ktp' => $ktp ?: null,
                'address_shipping' => $shipping ?: null,
                'address' => $shipping ?: null,
                'notes' => $cleaned !== '' ? $cleaned : null,
            ])->save();
        }

        // Partner master rows only have one address → use shipping/domicile
        DB::statement("
            UPDATE partner.agents a
            SET address = c.address_shipping
            FROM customer.customers c
            WHERE a.customer_id = c.id
              AND c.address_shipping IS NOT NULL
              AND c.address_shipping <> ''
              AND a.deleted_at IS NULL
        ");

        DB::statement("
            UPDATE partner.resellers r
            SET address = c.address_shipping
            FROM customer.customers c
            WHERE r.customer_id = c.id
              AND c.address_shipping IS NOT NULL
              AND c.address_shipping <> ''
              AND r.deleted_at IS NULL
        ");
    }
}
