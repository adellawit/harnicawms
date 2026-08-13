<?php

namespace App\Services\Partner;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Partner\Agent;
use App\Models\Partner\Reseller;
use App\Services\Geo\NominatimGeocoder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ForediCustomerCoordsUpdateService
{
    public function __construct(
        private readonly NominatimGeocoder $geocoder
    ) {}

    /**
     * @param  callable(string): void|null  $onProgress
     * @return array{
     *   parsed: int,
     *   updated_excel: int,
     *   updated_geocode: int,
     *   skipped_no_coords: int,
     *   not_found: int,
     *   rows: list<array{type: string, name: string, phone: string, code: string, status: string, lat: ?float, long: ?float}>
     * }
     */
    public function update(
        string $filePath,
        string $companyCode = 'SUHARA-001',
        bool $dryRun = false,
        bool $skipGeocode = false,
        ?callable $onProgress = null
    ): array {
        if (! is_file($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $company = BusinessUnit::where('code', $companyCode)->whereNull('deleted_at')->first();
        if (! $company) {
            throw new \RuntimeException("Company not found: {$companyCode}");
        }

        $agents = $this->readSheetRows($filePath, 'Agen');
        $resellers = $this->readSheetRows($filePath, 'Reseller');

        $stats = [
            'parsed' => count($agents) + count($resellers),
            'updated_excel' => 0,
            'updated_geocode' => 0,
            'skipped_no_coords' => 0,
            'not_found' => 0,
            'rows' => [],
        ];

        foreach ($agents as $index => $row) {
            $this->processRow(
                type: 'AGENT',
                companyId: $company->id,
                row: $row,
                seq: $index + 1,
                dryRun: $dryRun,
                skipGeocode: $skipGeocode,
                onProgress: $onProgress,
                stats: $stats,
            );
        }

        foreach ($resellers as $index => $row) {
            $this->processRow(
                type: 'RESELLER',
                companyId: $company->id,
                row: $row,
                seq: $index + 1,
                dryRun: $dryRun,
                skipGeocode: $skipGeocode,
                onProgress: $onProgress,
                stats: $stats,
            );
        }

        return $stats;
    }

    /**
     * @return list<array{name: string, phone: string, lat_raw: mixed, long_raw: mixed}>
     */
    public function readSheetRows(string $file, string $sheetName): array
    {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (! $sheet) {
            throw new \RuntimeException("Sheet not found: {$sheetName}");
        }

        $rows = [];
        $headerRow = 4;
        $maxRow = $sheet->getHighestDataRow();

        for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
            $name = trim((string) $sheet->getCell("C{$r}")->getValue());
            if ($name === '') {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'phone' => $this->firstPhone(trim((string) $sheet->getCell("J{$r}")->getValue())),
                'lat_raw' => $sheet->getCell("G{$r}")->getValue(),
                'long_raw' => $sheet->getCell("H{$r}")->getValue(),
            ];
        }

        return $rows;
    }

    /**
     * @param  array{name: string, phone: string, lat_raw: mixed, long_raw: mixed}  $row
     * @param  array{parsed: int, updated_excel: int, updated_geocode: int, skipped_no_coords: int, not_found: int, rows: list}  $stats
     * @param  callable(string): void|null  $onProgress
     */
    private function processRow(
        string $type,
        string $companyId,
        array $row,
        int $seq,
        bool $dryRun,
        bool $skipGeocode,
        ?callable $onProgress,
        array &$stats
    ): void {
        $code = $type === 'AGENT'
            ? sprintf('AG-%03d', $seq)
            : sprintf('RS-%03d', $seq);

        if ($onProgress) {
            $onProgress("{$type} {$code}: {$row['name']}");
        }

        $partner = $type === 'AGENT'
            ? $this->findAgent($companyId, $row['phone'], $code)
            : $this->findReseller($companyId, $row['phone'], $code);

        $customer = $partner?->customer;
        if (! $partner || ! $customer || $customer->trashed()) {
            $stats['not_found']++;
            $stats['rows'][] = [
                'type' => $type,
                'name' => $row['name'],
                'phone' => $row['phone'],
                'code' => $code,
                'status' => 'not_found',
                'lat' => null,
                'long' => null,
            ];

            return;
        }

        $coords = ForediCoordNormalizer::normalize($row['lat_raw'], $row['long_raw']);
        $source = 'excel';

        if ($coords === null && ! $skipGeocode && ! $dryRun) {
            $address = trim((string) ($customer->address_shipping ?: $customer->address ?: ''));
            if ($address !== '') {
                if ($onProgress) {
                    $onProgress("  Geocoding fallback: {$code}");
                }
                $coords = $this->geocoder->geocode($address, $customer->city ?: null);
                $source = $coords ? 'geocode' : 'none';
            }
        }

        if ($coords === null) {
            $stats['skipped_no_coords']++;
            $stats['rows'][] = [
                'type' => $type,
                'name' => $row['name'],
                'phone' => $row['phone'],
                'code' => $code,
                'status' => $dryRun && ForediCoordNormalizer::normalize($row['lat_raw'], $row['long_raw']) === null
                    ? 'would_skip_or_geocode'
                    : 'skipped_no_coords',
                'lat' => null,
                'long' => null,
            ];

            return;
        }

        if (! $dryRun) {
            $customer->forceFill([
                'lat' => $coords['lat'],
                'long' => $coords['long'],
            ])->save();
        }

        if ($source === 'excel') {
            $stats['updated_excel']++;
            $status = $dryRun ? 'would_update_excel' : 'updated_excel';
        } else {
            $stats['updated_geocode']++;
            $status = 'updated_geocode';
        }

        $stats['rows'][] = [
            'type' => $type,
            'name' => $row['name'],
            'phone' => $row['phone'],
            'code' => $code,
            'status' => $status,
            'lat' => $coords['lat'],
            'long' => $coords['long'],
        ];
    }

    private function findAgent(string $companyId, string $phone, string $code): ?Agent
    {
        $query = Agent::with('customer')->where('company_id', $companyId)->whereNull('deleted_at');

        if ($phone !== '') {
            $found = (clone $query)->where('phone', $phone)->first();
            if ($found) {
                return $found;
            }

            $digits = $this->digitsOnly($phone);
            if ($digits !== '') {
                $found = (clone $query)->get()->first(function (Agent $agent) use ($digits) {
                    return $this->digitsOnly((string) $agent->phone) === $digits;
                });
                if ($found) {
                    return $found;
                }
            }
        }

        return (clone $query)->where('code', $code)->first();
    }

    private function findReseller(string $companyId, string $phone, string $code): ?Reseller
    {
        $query = Reseller::with('customer')->where('company_id', $companyId)->whereNull('deleted_at');

        if ($phone !== '') {
            $found = (clone $query)->where('phone', $phone)->first();
            if ($found) {
                return $found;
            }

            $digits = $this->digitsOnly($phone);
            if ($digits !== '') {
                $found = (clone $query)->get()->first(function (Reseller $reseller) use ($digits) {
                    return $this->digitsOnly((string) $reseller->phone) === $digits;
                });
                if ($found) {
                    return $found;
                }
            }
        }

        return (clone $query)->where('code', $code)->first();
    }

    private function firstPhone(string $phoneRaw): string
    {
        $raw = trim($phoneRaw);
        if ($raw === '' || $raw === '-') {
            return '';
        }

        $parts = preg_split('/\s*\/\s*/', $raw) ?: [$raw];
        $first = trim($parts[0] ?? '');

        return $this->normalizePhoneDisplay($first);
    }

    private function normalizePhoneDisplay(string $phone): string
    {
        $phone = trim($phone);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return $phone;
        }

        if (str_starts_with($digits, '62') && strlen($digits) > 3) {
            $digits = '0' . substr($digits, 2);
        }

        if (strlen($digits) >= 10 && str_starts_with($digits, '08')) {
            return $digits;
        }

        return $digits !== '' ? $digits : $phone;
    }

    private function digitsOnly(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($digits, '62') && strlen($digits) > 3) {
            $digits = '0' . substr($digits, 2);
        }

        return $digits;
    }
}
