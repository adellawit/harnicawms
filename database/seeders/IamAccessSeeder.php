<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\IamAccess;
use Illuminate\Support\Facades\DB;

class IamAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accesses = [
            [
                'id' => '87d14961-0c14-474f-a6fa-b1130b521d39',
                'role_id' => '147c8a8e-52dc-4a79-a8ce-acb612b6e484', // Super Admin
                'is_notification' => false,
            ],
            [
                'id' => 'b0763f22-c9d1-41de-b7b9-28b523a7a354',
                'role_id' => '08d263b7-2c3b-43f0-a49b-b80d9d4b7685', // Administrator
                'is_notification' => false,
            ],
            [
                'id' => '2ac6f6a1-7b8c-4d9e-9f10-111213141517',
                'role_id' => '2ac6f6a1-7b8c-4d9e-9f10-111213141516', // Agent
                'is_notification' => false,
            ],
        ];

        foreach ($accesses as $access) {
            IamAccess::updateOrCreate(
                ['id' => $access['id']],
                $access
            );
        }
    }
}
