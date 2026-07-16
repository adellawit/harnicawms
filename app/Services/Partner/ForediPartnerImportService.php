<?php

namespace App\Services\Partner;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Partner\Agent;
use App\Models\Partner\AgentResellerAssignment;
use App\Models\Partner\Reseller;
use App\Models\Warehouse;
use App\Services\Geo\NominatimGeocoder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ForediPartnerImportService
{
    public const HOLDING_CODE = 'AG-HOLDING';

    public function __construct(
        private readonly NominatimGeocoder $geocoder
    ) {}

    /**
     * @param  callable(string): void|null  $onProgress
     * @return array{
     *   groups: int,
     *   agents_created: int,
     *   agents_updated: int,
     *   resellers_created: int,
     *   resellers_updated: int,
     *   geocoded: int,
     *   geocode_failed: int,
     *   agents_parsed: int,
     *   resellers_parsed: int
     * }
     */
    public function import(
        string $filePath,
        string $companyCode = 'SUHARA-001',
        string $branchCode = 'SUHARA-BDG-001',
        bool $withGeocode = true,
        ?callable $onProgress = null
    ): array {
        if (! is_file($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $company = BusinessUnit::where('code', $companyCode)->whereNull('deleted_at')->first();
        $branch = BusinessUnit::where('code', $branchCode)->whereNull('deleted_at')->first();

        if (! $company) {
            throw new \RuntimeException("Company not found: {$companyCode}");
        }
        if (! $branch) {
            throw new \RuntimeException("Branch not found: {$branchCode}");
        }

        $agents = $this->readSheetRows($filePath, 'Agen');
        $resellers = $this->readSheetRows($filePath, 'Reseller');

        $stats = [
            'groups' => 0,
            'agents_created' => 0,
            'agents_updated' => 0,
            'resellers_created' => 0,
            'resellers_updated' => 0,
            'geocoded' => 0,
            'geocode_failed' => 0,
            'agents_parsed' => count($agents),
            'resellers_parsed' => count($resellers),
        ];

        DB::transaction(function () use ($company, $branch, $agents, $resellers, $withGeocode, $onProgress, &$stats) {
            [$agentGroup, $resellerGroup, $createdGroups] = $this->ensureGroups($branch->id);
            $stats['groups'] = $createdGroups;

            $holding = $this->ensureHoldingAgent($company->id);

            foreach ($agents as $index => $row) {
                $result = $this->upsertAgent($company->id, $agentGroup, $row, $index + 1, $withGeocode, $onProgress);
                $stats[$result['created'] ? 'agents_created' : 'agents_updated']++;
                $this->tallyGeocode($stats, $result['geocoded']);
            }

            foreach ($resellers as $index => $row) {
                $result = $this->upsertReseller($company->id, $resellerGroup, $holding, $row, $index + 1, $withGeocode, $onProgress);
                $stats[$result['created'] ? 'resellers_created' : 'resellers_updated']++;
                $this->tallyGeocode($stats, $result['geocoded']);
            }
        });

        return $stats;
    }

    /**
     * @return list<array{name: string, phone: string, phone_raw: string, address: string, address_shipping: string, address_ktp: string, city: string, notes: string, marketplace: string}>
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

            $addressKtp = trim((string) $sheet->getCell("E{$r}")->getValue());
            $addressDomisili = trim((string) $sheet->getCell("F{$r}")->getValue());
            $cityRaw = trim((string) $sheet->getCell("G{$r}")->getValue());
            $phoneRaw = trim((string) $sheet->getCell("H{$r}")->getValue());
            $marketplace = trim((string) $sheet->getCell("I{$r}")->getValue());
            $regDate = trim((string) $sheet->getCell("J{$r}")->getValue());
            $source = trim((string) $sheet->getCell("K{$r}")->getValue());
            $catatan = trim((string) $sheet->getCell("L{$r}")->getValue());
            $birth = trim((string) $sheet->getCell("D{$r}")->getValue());

            $addressShipping = $this->resolveDomisiliAddress($addressDomisili, $addressKtp);
            $city = $this->parseCity($cityRaw);
            $phone = $this->firstPhone($phoneRaw);

            $noteParts = array_filter([
                $birth !== '' ? "TTL: {$birth}" : null,
                $marketplace !== '' && $marketplace !== '-' ? "Marketplace: {$marketplace}" : null,
                $regDate !== '' && $regDate !== '-' ? "Registrasi: {$regDate}" : null,
                $source !== '' ? "Sumber: {$source}" : null,
                $catatan !== '' ? "Catatan: {$catatan}" : null,
                'Imported from Daftar_Agent_dan_Reseller_Foredi.xlsx',
            ]);

            $rows[] = [
                'name' => $name,
                'phone' => $phone,
                'phone_raw' => $phoneRaw,
                'address' => $addressShipping,
                'address_shipping' => $addressShipping,
                'address_ktp' => $addressKtp !== '' ? $addressKtp : $addressShipping,
                'city' => $city,
                'notes' => implode("\n", $noteParts),
                'marketplace' => $marketplace,
            ];
        }

        return $rows;
    }

    private function tallyGeocode(array &$stats, ?bool $geocoded): void
    {
        if ($geocoded === true) {
            $stats['geocoded']++;
        } elseif ($geocoded === false) {
            $stats['geocode_failed']++;
        }
    }

    private function ensureGroups(string $branchId): array
    {
        $created = 0;

        $agentGroup = CustomerGroup::firstOrCreate(
            ['branch_id' => $branchId, 'code' => 'AGENT'],
            [
                'name' => 'Agent',
                'description' => 'Partner Agent Foredi/Harnica',
                'default_discount' => 0,
                'allow_credit' => false,
                'payment_term_days' => 0,
                'earn_point' => false,
                'point_multiplier' => 1,
                'sort_order' => 10,
                'is_active' => true,
            ]
        );
        if ($agentGroup->wasRecentlyCreated) {
            $created++;
        }

        $resellerGroup = CustomerGroup::firstOrCreate(
            ['branch_id' => $branchId, 'code' => 'RESELLER'],
            [
                'name' => 'Reseller',
                'description' => 'Partner Reseller Foredi/Harnica',
                'default_discount' => 0,
                'allow_credit' => false,
                'payment_term_days' => 0,
                'earn_point' => false,
                'point_multiplier' => 1,
                'sort_order' => 11,
                'is_active' => true,
            ]
        );
        if ($resellerGroup->wasRecentlyCreated) {
            $created++;
        }

        return [$agentGroup, $resellerGroup, $created];
    }

    private function ensureHoldingAgent(string $companyId): Agent
    {
        return Agent::firstOrCreate(
            [
                'company_id' => $companyId,
                'code' => self::HOLDING_CODE,
            ],
            [
                'customer_id' => null,
                'name' => 'Unassigned / HQ',
                'phone' => null,
                'address' => null,
                'city' => null,
                'status' => 'active',
                'approval_status' => 'approved',
                'approved_at' => now(),
                'notes' => 'Holding agent for Foredi resellers without mapped parent agent. Resellers can be reassigned later.',
            ]
        );
    }

    /**
     * @param  array{name: string, phone: string, phone_raw: string, address: string, address_ktp: string, city: string, notes: string}  $row
     * @param  callable(string): void|null  $onProgress
     * @return array{created: bool, geocoded: bool|null}
     */
    private function upsertAgent(
        string $companyId,
        CustomerGroup $group,
        array $row,
        int $seq,
        bool $withGeocode,
        ?callable $onProgress
    ): array {
        $code = sprintf('AG-%03d', $seq);
        $existing = $this->findAgentByPhoneOrCode($companyId, $row['phone'], $code);
        $created = $existing === null;

        $coords = $this->maybeGeocode($row, $withGeocode, $onProgress);
        $customer = $this->upsertCustomer(
            $group,
            $existing?->customer,
            $row,
            'CUST-' . $code,
            Customer::TYPE_AGENT,
            $coords
        );

        if ($existing) {
            $existing->update([
                'customer_id' => $customer->id,
                'name' => $row['name'],
                'phone' => $row['phone'] ?: $existing->phone,
                'address' => $row['address'] ?: $existing->address,
                'city' => $row['city'] ?: $existing->city,
                'notes' => $row['notes'],
                'status' => 'active',
                'approval_status' => 'approved',
                'approved_at' => $existing->approved_at ?: now(),
            ]);
            $agent = $existing->fresh();
        } else {
            $agent = Agent::create([
                'company_id' => $companyId,
                'customer_id' => $customer->id,
                'code' => $code,
                'name' => $row['name'],
                'phone' => $row['phone'] ?: null,
                'address' => $row['address'] ?: null,
                'city' => $row['city'] ?: null,
                'status' => 'active',
                'approval_status' => 'approved',
                'approved_at' => now(),
                'notes' => $row['notes'],
            ]);
        }

        $this->ensureAgentWarehouse($agent);

        return [
            'created' => $created,
            'geocoded' => $this->geocodeStatus($coords, $withGeocode, $row['address']),
        ];
    }

    /**
     * @param  array{name: string, phone: string, phone_raw: string, address: string, address_ktp: string, city: string, notes: string}  $row
     * @param  callable(string): void|null  $onProgress
     * @return array{created: bool, geocoded: bool|null}
     */
    private function upsertReseller(
        string $companyId,
        CustomerGroup $group,
        Agent $holding,
        array $row,
        int $seq,
        bool $withGeocode,
        ?callable $onProgress
    ): array {
        $code = sprintf('RS-%03d', $seq);
        $existing = $this->findResellerByPhoneOrCode($companyId, $row['phone'], $code);
        $created = $existing === null;

        $coords = $this->maybeGeocode($row, $withGeocode, $onProgress);
        $customer = $this->upsertCustomer(
            $group,
            $existing?->customer,
            $row,
            'CUST-' . $code,
            Customer::TYPE_RESELLER,
            $coords
        );

        if ($existing) {
            $existing->update([
                'customer_id' => $customer->id,
                'agent_id' => $holding->id,
                'name' => $row['name'],
                'phone' => $row['phone'] ?: $existing->phone,
                'address' => $row['address'] ?: $existing->address,
                'city' => $row['city'] ?: $existing->city,
                'notes' => $row['notes'],
                'status' => 'active',
            ]);
            $reseller = $existing->fresh();
        } else {
            $reseller = Reseller::create([
                'company_id' => $companyId,
                'agent_id' => $holding->id,
                'customer_id' => $customer->id,
                'code' => $code,
                'name' => $row['name'],
                'phone' => $row['phone'] ?: null,
                'address' => $row['address'] ?: null,
                'city' => $row['city'] ?: null,
                'status' => 'active',
                'notes' => $row['notes'],
            ]);
        }

        AgentResellerAssignment::where('reseller_id', $reseller->id)->update(['is_active' => false]);
        AgentResellerAssignment::create([
            'agent_id' => $holding->id,
            'reseller_id' => $reseller->id,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ]);

        return [
            'created' => $created,
            'geocoded' => $this->geocodeStatus($coords, $withGeocode, $row['address']),
        ];
    }

    /**
     * @param  array{lat: float, long: float}|null  $coords
     */
    private function geocodeStatus(?array $coords, bool $withGeocode, string $address): ?bool
    {
        if (! $withGeocode || $address === '') {
            return null;
        }

        return $coords !== null;
    }

    /**
     * @param  array{name: string, phone: string, address: string, address_shipping?: string, address_ktp?: string, city: string, notes: string}  $row
     * @param  array{lat: float, long: float}|null  $coords
     */
    private function upsertCustomer(
        CustomerGroup $group,
        ?Customer $existing,
        array $row,
        string $code,
        string $customerType,
        ?array $coords
    ): Customer {
        $shipping = $row['address_shipping'] ?? $row['address'] ?? null;
        $ktp = $row['address_ktp'] ?? null;

        $payload = [
            'customer_group_id' => $group->id,
            'name' => $row['name'],
            'phone' => $row['phone'] ?: null,
            'mobile' => $row['phone'] ?: null,
            'address_ktp' => $ktp ?: null,
            'address_shipping' => $shipping ?: null,
            // Keep legacy address in sync with shipping for existing consumers (POS, etc.)
            'address' => $shipping ?: null,
            'city' => $row['city'] ?: null,
            'country' => 'Indonesia',
            'customer_type' => $customerType,
            'notes' => $row['notes'],
            'is_active' => true,
            'has_app_access' => false,
        ];

        if ($coords) {
            $payload['lat'] = $coords['lat'];
            $payload['long'] = $coords['long'];
        }

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        $byPhone = $row['phone'] !== ''
            ? Customer::where('customer_group_id', $group->id)
                ->where(function ($q) use ($row) {
                    $q->where('phone', $row['phone'])->orWhere('mobile', $row['phone']);
                })
                ->whereNull('deleted_at')
                ->first()
            : null;

        if ($byPhone) {
            $byPhone->update($payload);

            return $byPhone->fresh();
        }

        $payload['code'] = $this->uniqueCustomerCode($group->id, $code);

        return Customer::create($payload);
    }

    private function ensureAgentWarehouse(Agent $agent): void
    {
        $warehouse = Warehouse::firstOrCreate(
            [
                'company_id' => $agent->company_id,
                'owner_type' => 'AGENT',
                'owner_id' => $agent->id,
                'is_default' => true,
            ],
            [
                'branch_id' => null,
                'warehouse_type_code' => 'GENERAL',
                'code' => 'AG-' . $agent->code . '-WH',
                'name' => 'Gudang ' . $agent->name,
                'short_name' => $agent->code,
                'phone' => $agent->phone,
                'address' => $agent->address,
                'city' => $agent->city,
                'province' => $agent->province,
                'postal_code' => $agent->postal_code,
                'country' => 'Indonesia',
                'is_inventory_active' => true,
                'is_active' => true,
                'notes' => 'Auto-created for partner Agent (Foredi import).',
            ]
        );

        if (! $agent->default_warehouse_id) {
            $agent->update(['default_warehouse_id' => $warehouse->id]);
        }
    }

    private function findAgentByPhoneOrCode(string $companyId, string $phone, string $code): ?Agent
    {
        $query = Agent::where('company_id', $companyId)->whereNull('deleted_at');

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

    private function findResellerByPhoneOrCode(string $companyId, string $phone, string $code): ?Reseller
    {
        $query = Reseller::where('company_id', $companyId)->whereNull('deleted_at');

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

    /**
     * @param  array{address: string, city: string}  $row
     * @param  callable(string): void|null  $onProgress
     * @return array{lat: float, long: float}|null
     */
    private function maybeGeocode(array $row, bool $withGeocode, ?callable $onProgress): ?array
    {
        if (! $withGeocode || $row['address'] === '') {
            return null;
        }

        if ($onProgress) {
            $onProgress('Geocoding: ' . mb_substr($row['address'], 0, 60) . '...');
        }

        return $this->geocoder->geocode($row['address'], $row['city'] ?: null);
    }

    private function uniqueCustomerCode(string $groupId, string $preferred): string
    {
        if (! Customer::where('customer_group_id', $groupId)->where('code', $preferred)->exists()) {
            return $preferred;
        }

        $i = 1;
        do {
            $code = $preferred . '-' . $i;
            $i++;
        } while (Customer::where('customer_group_id', $groupId)->where('code', $code)->exists());

        return $code;
    }

    private function resolveDomisiliAddress(string $domisili, string $ktp): string
    {
        $dom = trim($domisili);
        if ($dom === '' || strcasecmp($dom, 'Idem (sesuai KTP)') === 0 || strcasecmp($dom, 'Sesuai KTP') === 0) {
            return trim($ktp);
        }

        return $dom;
    }

    private function parseCity(string $cityRaw): string
    {
        $city = trim($cityRaw);
        if ($city === '') {
            return '';
        }
        if (str_contains($city, '/')) {
            return trim(explode('/', $city, 2)[0]);
        }

        return $city;
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
