<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => "147c8a8e-52dc-4a79-a8ce-acb612b6e484",
                'name' => 'Super Admin',
            ],
            [
                'id' => "08d263b7-2c3b-43f0-a49b-b80d9d4b7685",
                'name' => 'Administrator',
            ],
            [
                'id' => "629b1f65-3214-4d6e-9876-456321987654",
                'name' => 'Manager',
            ],
            [
                'id' => "9b7d5f3e-1a2b-4c3d-5e6f-7a8b9c0d1e2f",
                'name' => 'Staff',
            ],
            [
                'id' => "1f1e2b3c-4d5e-4789-a0b1-c2d3e4f5a6b7",
                'name' => 'Finance',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['id' => $role['id']],
                $role
            );
        }
    }
}
