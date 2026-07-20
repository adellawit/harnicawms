<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Training\AcademySetting;
use App\Models\Training\Course;
use App\Models\Training\CourseAccess;
use App\Models\Training\CourseMaterial;
use App\Models\Training\MaterialProgress;
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

        $showProgress = AcademySetting::current()->show_progress_percentage;

        return view('academy.dashboard', compact('courses', 'progressByCourse', 'stats', 'continue', 'showProgress'));
    }

    public function course(string $courseId)
    {
        $userId = Auth::id();
        $course = Course::published()->with(['category', 'modules.materials'])->findOrFail($courseId);

        $completedIds = array_flip($this->progress->completedMaterialIds($userId));
        $progress = $this->progress->courseProgress($course, array_keys($completedIds));

        $showProgress = AcademySetting::current()->show_progress_percentage;

        return view('academy.course', compact('course', 'progress', 'completedIds', 'showProgress'));
    }

    public function material(string $materialId)
    {
        $userId = Auth::id();
        $material = CourseMaterial::with('module.course')->findOrFail($materialId);
        $course = $material->module?->course;

        abort_if(! $course || ! $course->isPublished(), 404);

        // Log "viewed" (idempotent — keep first viewed_at, refresh nothing else).
        MaterialProgress::firstOrCreate(
            ['user_id' => $userId, 'material_id' => $material->id],
            ['viewed_at' => now()]
        );

        // Upsert course access (resume pointer + last accessed).
        $access = CourseAccess::firstOrNew(['user_id' => $userId, 'course_id' => $course->id]);
        $access->first_opened_at = $access->first_opened_at ?: now();
        $access->last_accessed_at = now();
        $access->last_material_id = $material->id;
        $access->save();

        // Ordered material list across the course for prev/next.
        $ordered = $course->loadMissing('modules.materials')->modules
            ->flatMap(fn ($m) => $m->materials)
            ->values();
        $idx = $ordered->search(fn ($m) => $m->id === $material->id);
        $prev = $idx > 0 ? $ordered[$idx - 1] : null;
        $next = ($idx !== false && $idx < $ordered->count() - 1) ? $ordered[$idx + 1] : null;

        $isCompleted = MaterialProgress::where('user_id', $userId)
            ->where('material_id', $material->id)
            ->whereNotNull('completed_at')
            ->exists();

        return view('academy.material', compact('material', 'course', 'prev', 'next', 'isCompleted'));
    }

    public function complete(string $materialId)
    {
        $userId = Auth::id();
        $material = CourseMaterial::with('module.course')->findOrFail($materialId);
        $course = $material->module?->course;
        abort_if(! $course || ! $course->isPublished(), 404);

        $progress = MaterialProgress::firstOrNew(['user_id' => $userId, 'material_id' => $material->id]);
        $progress->viewed_at = $progress->viewed_at ?: now();
        $progress->completed_at = now();
        $progress->save();

        return redirect()->route('academy.materials.show', $material->id)->with('success', 'Materi ditandai selesai.');
    }
}
