<?php

namespace App\Console\Commands;

use App\Services\Partner\ForediCustomerCoordsUpdateService;
use Illuminate\Console\Command;

class UpdateForediCustomerCoordsCommand extends Command
{
    protected $signature = 'partners:update-foredi-coords
        {--file=docs/Daftar_Agent_dan_Reseller_Foredi.xlsx : Path to the Excel file}
        {--company=SUHARA-001 : Company business unit code}
        {--dry-run : Parse/match/report without writing to DB (no Nominatim)}
        {--skip-geocode : Do not fall back to Nominatim when Excel coords are invalid}';

    protected $description = 'Update existing Foredi Agent/Reseller customer lat/long from Excel (÷100000), with optional Nominatim fallback';

    public function handle(ForediCustomerCoordsUpdateService $updater): int
    {
        $file = base_path($this->option('file'));
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry-run — no DB writes, no Nominatim calls.');
        }

        try {
            $stats = $updater->update(
                filePath: $file,
                companyCode: (string) $this->option('company'),
                dryRun: $dryRun,
                skipGeocode: (bool) $this->option('skip-geocode'),
                onProgress: fn (string $message) => $this->line('  ' . $message),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf('Parsed %d rows from Excel.', $stats['parsed']));
        $this->table(
            ['Metric', 'Count'],
            [
                ['updated_excel', $stats['updated_excel']],
                ['updated_geocode', $stats['updated_geocode']],
                ['skipped_no_coords', $stats['skipped_no_coords']],
                ['not_found', $stats['not_found']],
            ]
        );

        if ($stats['rows'] !== []) {
            $this->table(
                ['Type', 'Code', 'Name', 'Phone', 'Status', 'Lat', 'Long'],
                collect($stats['rows'])->map(fn (array $r) => [
                    $r['type'],
                    $r['code'],
                    $r['name'],
                    $r['phone'],
                    $r['status'],
                    $r['lat'] !== null ? number_format($r['lat'], 5, '.', '') : '-',
                    $r['long'] !== null ? number_format($r['long'], 5, '.', '') : '-',
                ])->all()
            );
        }

        if ($dryRun) {
            $this->warn('Dry-run complete — nothing written.');
        } else {
            $this->info('Coordinate update complete.');
        }

        return self::SUCCESS;
    }
}
