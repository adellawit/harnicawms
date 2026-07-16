<?php

namespace App\Console\Commands;

use App\Services\Partner\ForediPartnerImportService;
use Illuminate\Console\Command;

class ImportForediPartnersCommand extends Command
{
    protected $signature = 'partners:import-foredi
        {--file=docs/Daftar_Agent_dan_Reseller_Foredi.xlsx : Path to the Excel file}
        {--company=SUHARA-001 : Company business unit code}
        {--branch=SUHARA-BDG-001 : Branch business unit code for customer groups}
        {--dry-run : Parse and report without writing to DB}
        {--skip-geocode : Skip Nominatim lat/long lookup}';

    protected $description = 'Import Foredi Agent & Reseller list into customer + partner schemas';

    public function handle(ForediPartnerImportService $importer): int
    {
        $file = base_path($this->option('file'));
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $agents = $importer->readSheetRows($file, 'Agen');
            $resellers = $importer->readSheetRows($file, 'Reseller');
            $this->info(sprintf('Parsed %d agents and %d resellers from Excel.', count($agents), count($resellers)));
            $this->table(['#', 'Type', 'Name', 'Phone', 'City', 'Address'], array_merge(
                collect($agents)->map(fn ($r, $i) => [$i + 1, 'AGENT', $r['name'], $r['phone'], $r['city'], mb_substr($r['address'], 0, 50)])->all(),
                collect($resellers)->map(fn ($r, $i) => [$i + 1, 'RESELLER', $r['name'], $r['phone'], $r['city'], mb_substr($r['address'], 0, 50)])->all(),
            ));
            $this->warn('Dry-run only — no DB writes.');

            return self::SUCCESS;
        }

        try {
            $stats = $importer->import(
                filePath: $file,
                companyCode: (string) $this->option('company'),
                branchCode: (string) $this->option('branch'),
                withGeocode: ! $this->option('skip-geocode'),
                onProgress: fn (string $message) => $this->line('  ' . $message),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Parsed %d agents and %d resellers from Excel.',
            $stats['agents_parsed'],
            $stats['resellers_parsed']
        ));
        $this->info('Import complete.');
        $this->table(
            ['Metric', 'Count'],
            collect($stats)
                ->except(['agents_parsed', 'resellers_parsed'])
                ->map(fn ($v, $k) => [$k, $v])
                ->values()
                ->all()
        );

        return self::SUCCESS;
    }
}
