<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Models\Partner\Agent;
use App\Models\Training\Course;
use App\Models\Training\CourseAccess;
use App\Services\Training\ProgressService;

class ReportController extends Controller
{
    public function __construct(private readonly ProgressService $progress)
    {
    }

    public function index()
    {
        $courses = Course::published()->with('modules.materials')->get();

        // Last-activity per user (single grouped query).
        $lastActivity = CourseAccess::selectRaw('user_id, MAX(last_accessed_at) as last_at')
            ->groupBy('user_id')
            ->pluck('last_at', 'user_id');

        $agents = Agent::with('user')
            ->whereNotNull('user_id')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($agents as $agent) {
            $completedIds = $this->progress->completedMaterialIds($agent->user_id);

            $coursesCompleted = 0;
            foreach ($courses as $course) {
                $p = $this->progress->courseProgress($course, $completedIds);
                if ($p['total_materials'] > 0 && $p['percent'] >= 100) {
                    $coursesCompleted++;
                }
            }

            $rows[] = [
                'name' => $agent->name,
                'code' => $agent->code,
                'courses_completed' => $coursesCompleted,
                'materials_completed' => count($completedIds),
                'last_activity' => $lastActivity[$agent->user_id] ?? null,
            ];
        }

        return view('admin.training.reports.index', [
            'rows' => $rows,
            'coursesTotal' => $courses->count(),
        ]);
    }
}
