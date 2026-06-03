<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $divisions = [
            [
                'name' => 'IT',
                'code' => 'IT',
            ],
            [
                'name' => 'Logistics',
                'code' => 'LOG',
            ],
            [
                'name' => 'Finance',
                'code' => 'FIN',
            ],
            [
                'name' => 'Marketing',
                'code' => 'MKT',
            ],
            [
                'name' => 'HRD',
                'code' => 'HRD',
            ],
        ];

        foreach ($divisions as $division) {
            Division::updateOrCreate(
                ['name' => $division['name']],
                $division
            );
        }

        $this->command->info('Divisions seeded successfully!');
    }
}
