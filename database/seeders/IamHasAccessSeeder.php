<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\IamHasAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IamHasAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminIamAccessId = '87d14961-0c14-474f-a6fa-b1130b521d39';

        // Get all menus from database
        $menus = DB::table('master_data.menus')->whereNull('deleted_at')->orderBy('order_number')->get();

        $hasAccesses = [];

        foreach ($menus as $menu) {
            // Generate a predictable UUID based on menu ID and iam_access_id
            $uuid = Str::uuid()->toString();

            $hasAccesses[] = [
                'id' => $uuid,
                'iam_access_id' => $superAdminIamAccessId,
                'sidebar_menu_id' => $menu->id,
                'is_create' => true,
                'is_read' => true,
                'is_update' => true,
                'is_delete' => true,
                'is_custom_1' => false,
                'is_custom_2' => false,
                'is_custom_3' => false,
                'is_custom_4' => false,
                'is_custom_5' => false,
            ];
        }

        // Truncate table first (PostgreSQL friendly)
        DB::statement('TRUNCATE TABLE auth.iam_has_accesses CASCADE');

        // Insert all accesses using insert for better performance
        DB::table('auth.iam_has_accesses')->insert($hasAccesses);

        $this->command->info('IAM Has Access seeded successfully! Total menus: ' . count($hasAccesses));
    }
}
