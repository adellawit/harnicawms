<?php

namespace Database\Seeders;

use App\Models\Employees;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeSeeder extends Seeder
{
    public function run()
    {
        $faker = fake('id_ID');
        $totalEmployees = 10;

        $positionIds = DB::table('master_data.positions')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->values();

        $divisionIds = DB::table('master_data.divisions')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->values();

        $branchIds = DB::table('master_data.business_units')
            ->where('type_code', 'BRANCH')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->values();

        if ($positionIds->isEmpty() || $divisionIds->isEmpty() || $branchIds->isEmpty()) {
            $this->command?->warn('Skip EmployeeSeeder random data: positions/divisions/branches are not ready.');
            return;
        }

        $demoBranchId = $branchIds->first();
        $demoPositionId = $positionIds->first();
        $demoDivisionId = $divisionIds->first();

        $demoEmployee = Employees::firstOrNew(['email' => 'demo@wit.id']);
        if (! $demoEmployee->exists) {
            $demoEmployee->id = '63358944-52e5-4844-8e0f-b7687719926c';
        }
        $demoEmployee->fill([
            'position_id' => $demoPositionId,
            'division_id' => $demoDivisionId,
            'business_unit_id' => $demoBranchId,
            'employee_code' => 'WIT-EMP-000',
            'identity_number' => 'NIK-202600000000',
            'fullname' => 'Demo User',
            'nickname' => 'Demo',
            'gender' => 'Laki-laki',
            'place_of_birth' => 'Jakarta',
            'date_of_birth' => '1990-01-01',
            'marital_status' => 'Single',
            'number_of_dependents' => 0,
            'address' => 'Jl. Demo No. 1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '10110',
            'phone_number' => '081234567890',
            'join_date' => now()->subYears(3)->format('Y-m-d'),
            'employment_status' => 'Permanent',
            'employee_status' => 'Active',
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ]);
        $demoEmployee->save();

        for ($i = 1; $i <= $totalEmployees - 1; $i++) {
            $firstName = $faker->firstName();
            $lastName = $faker->lastName();
            $fullName = trim($firstName . ' ' . $lastName);
            $email = sprintf('employee%02d@wit.id', $i);

            $employee = [
                'position_id' => $positionIds->random(),
                'division_id' => $divisionIds->random(),
                'business_unit_id' => $branchIds->random(),
                'employee_code' => sprintf('WIT-EMP-%03d', $i),
                'identity_number' => sprintf('NIK-%012d', 202600000000 + $i),
                'fullname' => $fullName,
                'nickname' => $firstName,
                'gender' => $faker->randomElement(['Laki-laki', 'Perempuan']),
                'place_of_birth' => $faker->city(),
                'date_of_birth' => $faker->dateTimeBetween('-45 years', '-20 years')->format('Y-m-d'),
                'marital_status' => $faker->randomElement(['Single', 'Married']),
                'number_of_dependents' => $faker->numberBetween(0, 3),
                'address' => $faker->address(),
                'city' => $faker->city(),
                'province' => $faker->state(),
                'postal_code' => $faker->postcode(),
                'phone_number' => '08' . $faker->numerify('##########'),
                'email' => $email,
                'join_date' => $faker->dateTimeBetween('-5 years', '-6 months')->format('Y-m-d'),
                'employment_status' => $faker->randomElement(['Permanent', 'Contract', 'Probation']),
                'employee_status' => 'Active',
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
            ];

            $randomEmployee = Employees::firstOrNew(['email' => $email]);
            if (! $randomEmployee->exists) {
                $randomEmployee->id = (string) Str::uuid();
            }
            $randomEmployee->fill($employee);
            $randomEmployee->save();
        }

        $this->command?->info("Employees seeded successfully ({$totalEmployees} records incl. demo@wit.id).");
    }
}
