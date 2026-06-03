<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = database_path('seeders/data/customers_karyawan.json');

        if (! is_file($dataPath)) {
            $this->command?->error("Data file tidak ditemukan: {$dataPath}");

            return;
        }

        $rows = json_decode((string) file_get_contents($dataPath), true);

        if (! is_array($rows) || $rows === []) {
            $this->command?->error('Data customer karyawan kosong atau tidak valid.');

            return;
        }

        $branches = DB::table('master_data.business_units')
            ->where('type_code', 'BRANCH')
            ->where('is_active', true)
            ->pluck('id', 'name');

        if ($branches->isEmpty()) {
            $this->command?->error('Tidak ada cabang aktif. Jalankan BusinessUnitSeeder terlebih dahulu.');

            return;
        }

        DB::statement('TRUNCATE TABLE customer.customers CASCADE');
        DB::statement('TRUNCATE TABLE customer.customer_groups CASCADE');

        $groupByBranch = [];

        foreach ($branches as $branchName => $branchId) {
            $group = CustomerGroup::create([
                'branch_id' => $branchId,
                'code' => 'KARYAWAN',
                'name' => 'Karyawan',
                'description' => 'Pelanggan internal — karyawan',
                'default_discount' => 0,
                'allow_credit' => false,
                'payment_term_days' => 0,
                'earn_point' => true,
                'point_multiplier' => 1,
                'sort_order' => 10,
                'is_active' => true,
            ]);

            $groupByBranch[$branchName] = $group->id;
        }

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $branch = (string) ($row['branch'] ?? '');
            $groupId = $groupByBranch[$branch] ?? null;

            if ($groupId === null) {
                $this->command?->warn("Cabang tidak dikenali: {$branch} ({$row['code']})");
                $skipped++;

                continue;
            }

            Customer::create([
                'customer_group_id' => $groupId,
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'email' => $this->nullable($row['email'] ?? null),
                'phone' => $this->nullable($row['phone'] ?? null),
                'address' => $this->nullable($row['address'] ?? null),
                'city' => $this->nullable($row['city'] ?? null),
                'postal_code' => $this->nullable($row['postal_code'] ?? null),
                'country' => 'Indonesia',
                'customer_type' => 'KARYAWAN',
                'is_active' => true,
                'has_app_access' => filled($this->nullable($row['email'] ?? null)),
            ]);

            $created++;
        }

        $this->command?->info(sprintf(
            'Customer seeded: %d grup Karyawan, %d pelanggan (%d dilewati).',
            count($groupByBranch),
            $created,
            $skipped
        ));
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
