<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restore nested Training Academy + Marketing Center menus.
 * Replaces flat top-level order 11/12 (Training / Pengaturan).
 */
return new class extends Migration
{
    private const TRAINING_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000010';

    private const ACADEMY_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000011';

    private const SETTINGS_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000012';

    private const COURSES_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000014';

    private const MARKETING_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000020';

    private const MARKETING_CATEGORY_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000021';

    private const MARKETING_ASSETS_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000022';

    /** @var array<string, int> Shift existing top-level menus to free 6–7 */
    private const SHIFT_TOP_LEVEL = [
        'crm' => 8,
        'point_of_sales' => 9,
        'business' => 10,
        'reporting' => 11,
        'settings' => 12,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('master_data.menus')) {
            return;
        }

        $now = now();

        foreach (self::SHIFT_TOP_LEVEL as $code => $order) {
            DB::table('master_data.menus')
                ->where('code', $code)
                ->whereNull('parent_id')
                ->whereNull('deleted_at')
                ->update([
                    'order_number' => $order,
                    'updated_at' => $now,
                ]);
        }

        // Parent: Training Academy (toggle group)
        $this->upsertMenu(self::TRAINING_ID, [
            'parent_id' => null,
            'name' => 'Training Academy',
            'code' => 'training-academy',
            'text_sidebar' => 'Training Academy',
            'icon' => 'ti ti-school',
            'has_page' => true,
            'url_path' => 'training/academy',
            'route_name' => 'training.academy.home',
            'slug' => 'training-academy',
            'level_sidebar' => 1,
            'order_number' => 6,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
        ], $now);

        $this->upsertMenu(self::COURSES_ID, [
            'parent_id' => self::TRAINING_ID,
            'name' => 'Course',
            'code' => 'training-courses',
            'text_sidebar' => 'Course',
            'icon' => 'ti ti-blackboard',
            'has_page' => false,
            'url_path' => 'training/courses',
            'route_name' => 'training.courses.index',
            'slug' => 'training-courses',
            'level_sidebar' => 2,
            'order_number' => 1,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
        ], $now);

        $this->upsertMenu(self::ACADEMY_ID, [
            'parent_id' => self::TRAINING_ID,
            'name' => 'Academy',
            'code' => 'academy',
            'text_sidebar' => 'Academy',
            'icon' => 'ti ti-book',
            'has_page' => false,
            'url_path' => 'academy',
            'route_name' => 'academy.dashboard',
            'slug' => 'academy',
            'level_sidebar' => 2,
            'order_number' => 2,
            'has_create' => false,
            'has_update' => false,
            'has_read' => true,
            'has_delete' => false,
        ], $now);

        $this->upsertMenu(self::SETTINGS_ID, [
            'parent_id' => self::TRAINING_ID,
            'name' => 'Pengaturan Academy',
            'code' => 'training-academy-settings',
            'text_sidebar' => 'Pengaturan Academy',
            'icon' => 'ti ti-settings',
            'has_page' => false,
            'url_path' => 'training/settings',
            'route_name' => 'training.settings.edit',
            'slug' => 'training-academy-settings',
            'level_sidebar' => 2,
            'order_number' => 3,
            'has_create' => false,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => false,
        ], $now);

        // Parent: Marketing Center
        $this->upsertMenu(self::MARKETING_ID, [
            'parent_id' => null,
            'name' => 'Marketing Center',
            'code' => 'marketing-center',
            'text_sidebar' => 'Marketing Center',
            'icon' => 'ti ti-speakerphone',
            'has_page' => true,
            'url_path' => null,
            'route_name' => null,
            'slug' => 'marketing-center',
            'level_sidebar' => 1,
            'order_number' => 7,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
        ], $now);

        $this->upsertMenu(self::MARKETING_CATEGORY_ID, [
            'parent_id' => self::MARKETING_ID,
            'name' => 'Marketing Category',
            'code' => 'marketing-category',
            'text_sidebar' => 'Marketing Category',
            'icon' => 'ti ti-category',
            'has_page' => false,
            'url_path' => 'marketing/categories',
            'route_name' => 'marketing.categories.index',
            'slug' => 'marketing-category',
            'level_sidebar' => 2,
            'order_number' => 1,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
        ], $now);

        $this->upsertMenu(self::MARKETING_ASSETS_ID, [
            'parent_id' => self::MARKETING_ID,
            'name' => 'Marketing Assets',
            'code' => 'marketing-campaign',
            'text_sidebar' => 'Marketing Assets',
            'icon' => 'ti ti-photo',
            'has_page' => false,
            'url_path' => 'marketing/assets',
            'route_name' => 'marketing.assets.index',
            'slug' => 'marketing-campaign',
            'level_sidebar' => 2,
            'order_number' => 2,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
        ], $now);
    }

    public function down(): void
    {
        // Keep nested structure; flattening again is a separate product decision.
    }

    /** @param array<string, mixed> $payload */
    private function upsertMenu(string $id, array $payload, mixed $now): void
    {
        $defaults = [
            'is_label' => false,
            'has_custom1' => false,
            'has_custom2' => false,
            'has_custom3' => false,
            'has_custom4' => false,
            'has_custom5' => false,
        ];

        $exists = DB::table('master_data.menus')->where('id', $id)->exists();

        if ($exists) {
            DB::table('master_data.menus')->where('id', $id)->update(array_merge($defaults, $payload, [
                'deleted_at' => null,
                'updated_at' => $now,
            ]));

            return;
        }

        DB::table('master_data.menus')->insert(array_merge($defaults, $payload, [
            'id' => $id,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]));
    }
};
