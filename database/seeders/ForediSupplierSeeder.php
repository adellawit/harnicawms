<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Parameter;
use App\Models\ParameterDetail;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Seed supplier master for Suhara / Foredi (tipe Bahan Baku).
 *
 * 1. Pabrik Foredi
 * 2. Supplier Packaging
 *
 * Depends on: BusinessUnitSeeder (SUHARA-001 / SUHARA-BDG-001), ParameterSeeder (SUPPLIER)
 */
class ForediSupplierSeeder extends Seeder
{
    private const COMPANY_CODE = 'SUHARA-001';

    private const BRANCH_CODE = 'SUHARA-BDG-001';

    public function run(): void
    {
        $company = BusinessUnit::query()
            ->where('code', self::COMPANY_CODE)
            ->where('type_code', 'COMPANY')
            ->whereNull('deleted_at')
            ->first();

        $branch = BusinessUnit::query()
            ->where('code', self::BRANCH_CODE)
            ->whereNull('deleted_at')
            ->first();

        if (! $company || ! $branch) {
            $this->command?->error('Company/branch Suhara tidak ditemukan. Jalankan BusinessUnitSeeder dulu.');

            return;
        }

        $typeRawMaterial = $this->supplierTypeId('raw_material');

        $rows = [
            [
                'code' => 'SUP-00001',
                'name' => 'Pabrik Foredi',
                'supplier_type_id' => $typeRawMaterial,
                'contact' => null,
                'phone' => null,
                'email' => null,
                'address' => null,
                'is_ppn' => true,
                'ppn_rate' => 11,
            ],
            [
                'code' => 'SUP-00002',
                'name' => 'Supplier Packaging',
                'supplier_type_id' => $typeRawMaterial,
                'contact' => null,
                'phone' => null,
                'email' => null,
                'address' => null,
                'is_ppn' => true,
                'ppn_rate' => 11,
            ],
        ];

        foreach ($rows as $row) {
            Supplier::withTrashed()->updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'is_active' => true,
                    'deleted_at' => null,
                ])
            );
        }

        $this->command?->info('Foredi suppliers seeded: Pabrik Foredi (SUP-00001), Supplier Packaging (SUP-00002).');
    }

    private function supplierTypeId(string $key): ?string
    {
        $parameterId = Parameter::query()->where('code', 'SUPPLIER')->value('id');
        if (! $parameterId) {
            return null;
        }

        return ParameterDetail::query()
            ->where('parameter_id', $parameterId)
            ->where('key', $key)
            ->value('id');
    }
}
