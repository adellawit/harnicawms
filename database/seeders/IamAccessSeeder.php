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
                'role_id' => '147c8a8e-52dc-4a79-a8ce-acb612b6e484',
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
