<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Password: gunakan plain password. Model User punya cast 'hashed' sehingga
     * password akan di-hash otomatis saat disimpan. Jangan pakai Hash::make() di sini.
     */
    public function run(): void
    {
        $totalUsers = 10;
        $superAdminRoleId = '147c8a8e-52dc-4a79-a8ce-acb612b6e484';

        $demoEmployee = DB::table('human_resources.employees')
            ->where('email', 'demo@wit.id')
            ->whereNull('deleted_at')
            ->first(['id', 'fullname', 'business_unit_id']);

        if (! $demoEmployee) {
            $this->command?->warn('Skip UserSeeder: demo employee not found. Run EmployeeSeeder first.');
            return;
        }

        $demoNameParts = preg_split('/\s+/', trim((string) $demoEmployee->fullname), 2, PREG_SPLIT_NO_EMPTY) ?: ['Demo'];

        User::updateOrCreate(
            ['username' => 'demo@wit.id'],
            [
                'employee_id' => $demoEmployee->id,
                'role_id' => $superAdminRoleId,
                'current_business_unit_id' => $demoEmployee->business_unit_id,
                'first_name' => $demoNameParts[0],
                'last_name' => $demoNameParts[1] ?? $demoNameParts[0],
                'username' => 'demo@wit.id',
                'email' => 'demo@wit.id',
                'password' => 'demo2026*#',
                'url_image' => config('app.url') . '/assets/img/ars/avatar/user-default.jpg',
                'need_update_password' => false,
            ]
        );

        $employees = DB::table('human_resources.employees')
            ->whereNull('deleted_at')
            ->where('employee_status', 'Active')
            ->where('email', '!=', 'demo@wit.id')
            ->orderBy('created_at')
            ->limit($totalUsers - 1)
            ->get(['id', 'fullname', 'business_unit_id']);

        $roleIds = DB::table('master_data.roles')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->values();

        if ($employees->count() < ($totalUsers - 1) || $roleIds->isEmpty()) {
            $this->command?->warn('Skip UserSeeder random data: employees/roles are not ready.');
            return;
        }

        foreach ($employees as $index => $employee) {
            $nameParts = preg_split('/\s+/', trim((string) $employee->fullname), 2, PREG_SPLIT_NO_EMPTY) ?: ['User'];
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? $nameParts[0];
            $username = sprintf('user%02d@wit.id', $index + 1);

            $user = [
                'employee_id' => $employee->id,
                'role_id' => $roleIds->random(),
                'current_business_unit_id' => $employee->business_unit_id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => $username,
                'email' => $username,
                'password' => 'user12345',
                'url_image' => config('app.url') . '/assets/img/ars/avatar/user-default.jpg',
                'need_update_password' => true,
            ];

            User::updateOrCreate(
                ['username' => $user['username']],
                $user
            );
        }

        $this->command?->info("Users seeded successfully ({$totalUsers} records incl. demo@wit.id as default).");
    }
}
