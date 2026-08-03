<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flatten Training Academy sidebar:
 * - Top-level 11: Training Academy (training/academy)
 * - Top-level 12: Pengaturan Academy (training/settings)
 * Soft-delete nested Course/Academy children and Marketing Center (if present).
 * Move Accounting parent off order 11 so it does not collide.
 */
return new class extends Migration
{
    private const TRAINING_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000010';

    private const LEGACY_LEARN_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000011';

    private const SETTINGS_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000012';

    private const MARKETING_CENTER_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000020';

    private const ACCOUNTING_ID = 'a1c00001-f001-4001-8001-000000000001';

    public function up(): void
    {
        if (! Schema::hasTable('master_data.menus')) {
            return;
        }

        $now = now();

        // Free order 11 if Accounting still sits there (legacy seed/migration).
        $accounting = DB::table('master_data.menus')
            ->where('id', self::ACCOUNTING_ID)
            ->whereNull('deleted_at')
            ->first(['order_number']);

        if ($accounting && (int) $accounting->order_number === 11) {
            DB::table('master_data.menus')
                ->where('id', self::ACCOUNTING_ID)
                ->update([
                    'order_number' => 3,
                    'updated_at' => $now,
                ]);

            DB::table('master_data.menus')
                ->where('code', 'customer')
                ->whereNull('parent_id')
                ->whereNull('deleted_at')
                ->where('order_number', 3)
                ->update([
                    'order_number' => 4,
                    'updated_at' => $now,
                ]);
        }

        $this->upsertMenu(self::TRAINING_ID, [
            'parent_id' => null,
            'name' => 'Training Academy',
            'code' => 'training-academy',
            'text_sidebar' => 'Training Academy',
            'icon' => 'ti ti-school',
            'has_page' => false,
            'url_path' => 'training/academy',
            'route_name' => 'training.academy.home',
            'slug' => 'training-academy',
            'level_sidebar' => 1,
            'order_number' => 11,
            'is_label' => false,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
            'has_custom1' => false,
            'has_custom2' => false,
            'has_custom3' => false,
            'has_custom4' => false,
            'has_custom5' => false,
        ], $now);

        $this->upsertMenu(self::SETTINGS_ID, [
            'parent_id' => null,
            'name' => 'Pengaturan Academy',
            'code' => 'training-academy-settings',
            'text_sidebar' => 'Pengaturan Academy',
            'icon' => 'ti ti-settings',
            'has_page' => false,
            'url_path' => 'training/settings',
            'route_name' => 'training.settings.edit',
            'slug' => 'training-academy-settings',
            'level_sidebar' => 1,
            'order_number' => 12,
            'is_label' => false,
            'has_create' => false,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => false,
            'has_custom1' => false,
            'has_custom2' => false,
            'has_custom3' => false,
            'has_custom4' => false,
            'has_custom5' => false,
        ], $now);

        // Soft-delete nested children under Training Academy (Course / Academy / old Settings).
        DB::table('master_data.menus')
            ->where('parent_id', self::TRAINING_ID)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

        foreach ([self::LEGACY_LEARN_ID, self::MARKETING_CENTER_ID] as $id) {
            DB::table('master_data.menus')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        DB::table('master_data.menus')
            ->where('parent_id', self::MARKETING_CENTER_ID)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('master_data.menus')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereIn('url_path', [
                    'training/courses',
                    'academy',
                    'marketing/categories',
                    'marketing/assets',
                ])->orWhere(function ($q2) {
                    $q2->where('text_sidebar', 'Marketing Center')
                        ->whereNull('parent_id');
                });
            })
            ->whereNotIn('id', [self::TRAINING_ID, self::SETTINGS_ID])
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Intentionally no-op: restoring nested Marketing/Course tree is not desired.
    }

    /** @param array<string, mixed> $payload */
    private function upsertMenu(string $id, array $payload, mixed $now): void
    {
        $exists = DB::table('master_data.menus')->where('id', $id)->exists();

        if ($exists) {
            DB::table('master_data.menus')->where('id', $id)->update(array_merge($payload, [
                'deleted_at' => null,
                'updated_at' => $now,
            ]));

            return;
        }

        DB::table('master_data.menus')->insert(array_merge($payload, [
            'id' => $id,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]));
    }
};
