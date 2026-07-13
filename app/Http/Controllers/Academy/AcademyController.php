<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Training\Course;
use App\Models\Training\CourseAccess;
use App\Services\Training\ProgressService;
use Illuminate\Support\Facades\Auth;

class AcademyController extends Controller
{
    public function __construct(private readonly ProgressService $progress)
    {
    }

    public function dashboard()
    {
        $userId = Auth::id();
        $completedIds = $this->progress->completedMaterialIds($userId);

        $courses = Course::published()
            ->with(['category', 'modules.materials'])
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->get();

        $progressByCourse = [];
        foreach ($courses as $course) {
            $progressByCourse[$course->id] = $this->progress->courseProgress($course, $completedIds);
        }

        $stats = $this->progress->dashboardStats($courses, $completedIds);

        // "Sedang Dipelajari": most-recently accessed published course not yet 100%.
        $continue = null;
        $accesses = CourseAccess::where('user_id', $userId)
            ->orderByDesc('last_accessed_at')
            ->get();
        foreach ($accesses as $access) {
            $course = $courses->firstWhere('id', $access->course_id);
            if ($course && ($progressByCourse[$course->id]['percent'] ?? 0) < 100) {
                $continue = ['course' => $course, 'access' => $access, 'progress' => $progressByCourse[$course->id]];
                break;
            }
        }

        return view('academy.dashboard', compact('courses', 'progressByCourse', 'stats', 'continue'));
    }
}
