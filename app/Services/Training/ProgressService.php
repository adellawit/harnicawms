<?php

namespace App\Services\Training;

use App\Models\Training\Course;
use App\Models\Training\MaterialProgress;

class ProgressService
{
    /**
     * Material ids the given user has completed (completed_at set).
     *
     * @return array<int, string>
     */
    public function completedMaterialIds(string $userId): array
    {
        return MaterialProgress::query()
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->pluck('material_id')
            ->all();
    }

    /**
     * Progress for a single course. $course must have modules.materials loaded
     * (soft-deleted rows are already excluded by the models' SoftDeletes scope).
     *
     * @param  array<int, string>  $completedIds
     * @return array<string, mixed>
     */
    public function courseProgress(Course $course, array $completedIds): array
    {
        $completed = array_flip($completedIds);

        $totalMaterials = 0;
        $completedCount = 0;
        $modulesTotal = 0;
        $modulesCompleted = 0;
        $minutesDone = 0;
        $minutesRemaining = 0;
        $hasMinutes = false;

        foreach ($course->modules as $module) {
            $materials = $module->materials;
            if ($materials->isEmpty()) {
                continue; // empty modules do not count toward module totals
            }
            $modulesTotal++;
            $allDone = true;

            foreach ($materials as $material) {
                $totalMaterials++;
                $isDone = isset($completed[$material->id]);
                if ($isDone) {
                    $completedCount++;
                } else {
                    $allDone = false;
                }

                if ($material->estimated_minutes !== null) {
                    $hasMinutes = true;
                    if ($isDone) {
                        $minutesDone += $material->estimated_minutes;
                    } else {
                        $minutesRemaining += $material->estimated_minutes;
                    }
                }
            }

            if ($allDone) {
                $modulesCompleted++;
            }
        }

        $percent = $totalMaterials > 0
            ? (int) round($completedCount / $totalMaterials * 100)
            : 0;

        return [
            'total_materials' => $totalMaterials,
            'completed_count' => $completedCount,
            'percent' => $percent,
            'modules_total' => $modulesTotal,
            'modules_completed' => $modulesCompleted,
            'minutes_done' => $minutesDone,
            'minutes_remaining' => $minutesRemaining,
            'has_minutes' => $hasMinutes,
        ];
    }

    /**
     * Aggregate header stats across a set of (loaded) courses.
     *
     * @param  iterable<int, Course>  $courses
     * @param  array<int, string>  $completedIds
     * @return array<string, mixed>
     */
    public function dashboardStats(iterable $courses, array $completedIds): array
    {
        $modulesTotal = 0;
        $modulesCompleted = 0;
        $minutesDone = 0;
        $hasMinutes = false;

        foreach ($courses as $course) {
            $p = $this->courseProgress($course, $completedIds);
            $modulesTotal += $p['modules_total'];
            $modulesCompleted += $p['modules_completed'];
            $minutesDone += $p['minutes_done'];
            $hasMinutes = $hasMinutes || $p['has_minutes'];
        }

        return [
            'modules_total' => $modulesTotal,
            'modules_completed' => $modulesCompleted,
            'minutes_done' => $minutesDone,
            'has_minutes' => $hasMinutes,
        ];
    }
}
