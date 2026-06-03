<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run()
    {
        $positions = [
            [
                'id' => '0a0e68e0-106f-4bc9-99d7-4bd726d70976',
                'name' => 'Administrator',
                'code' => 'ADM',
            ],
            [
                'id' => '06f9ee8b-f26b-40a6-b9b3-62a39a0773a6',
                'name' => 'Staff',
                'code' => 'STA',
            ],
            [
                'id' => '659b2d1a-9027-473e-9012-728281997117',
                'name' => 'IT Support',
                'code' => 'ITS',
            ]
        ];

        foreach ($positions as $position) {
            Position::updateOrCreate(
                ['id' => $position['id']],
                $position
            );
        }

        $this->command->info('Positions seeded successfully!');
    }
}
