<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\MaterialRequest;
use App\Http\Requests\Training\ModuleRequest;
use App\Models\Training\Course;
use App\Models\Training\CourseMaterial;
use App\Models\Training\CourseModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CourseContentController extends Controller
{
    public function content(string $courseId)
    {
        $course = Course::with(['modules.materials'])->findOrFail($courseId);

        return view('admin.training.courses.content', compact('course'));
    }

    public function storeModule(ModuleRequest $request, string $courseId)
    {
        $course = Course::findOrFail($courseId);
        CourseModule::create([
            'course_id' => $course->id,
            'company_id' => $course->company_id,
            'title' => $request->string('title'),
            'description' => $request->input('description'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Modul ditambahkan.');
    }

    public function updateModule(ModuleRequest $request, string $courseId, string $moduleId)
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);
        $module->update([
            'title' => $request->string('title'),
            'description' => $request->input('description'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Modul diperbarui.');
    }

    public function destroyModule(string $courseId, string $moduleId)
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);
        $module->deleted_by = Auth::id();
        $module->save();
        $module->delete();

        return back()->with('success', 'Modul dihapus.');
    }

    public function storeMaterial(MaterialRequest $request, string $courseId, string $moduleId)
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);
        $data = $this->materialPayload($request);
        $data['module_id'] = $module->id;
        $data['company_id'] = $module->company_id;
        $data['created_by'] = Auth::id();

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('training/materials', 'public');
        }

        CourseMaterial::create($data);

        return back()->with('success', 'Materi ditambahkan.');
    }

    public function updateMaterial(MaterialRequest $request, string $courseId, string $moduleId, string $materialId)
    {
        $material = CourseMaterial::where('module_id', $moduleId)->findOrFail($materialId);
        $data = $this->materialPayload($request);
        $data['updated_by'] = Auth::id();

        if ($request->hasFile('file')) {
            if ($material->file_path) {
                Storage::disk('public')->delete($material->file_path);
            }
            $data['file_path'] = $request->file('file')->store('training/materials', 'public');
        }

        // Clear the field that does not belong to the chosen type.
        if ($data['type'] === 'youtube') {
            $data['file_path'] = null;
        } else {
            $data['youtube_url'] = null;
        }

        $material->update($data);

        return back()->with('success', 'Materi diperbarui.');
    }

    public function destroyMaterial(string $courseId, string $moduleId, string $materialId)
    {
        $material = CourseMaterial::where('module_id', $moduleId)->findOrFail($materialId);
        $material->deleted_by = Auth::id();
        $material->save();
        $material->delete();

        return back()->with('success', 'Materi dihapus.');
    }

    private function materialPayload(MaterialRequest $request): array
    {
        return [
            'title' => $request->string('title'),
            'type' => $request->input('type'),
            'youtube_url' => $request->input('type') === 'youtube' ? $request->input('youtube_url') : null,
            'estimated_minutes' => $request->filled('estimated_minutes') ? (int) $request->input('estimated_minutes') : null,
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
