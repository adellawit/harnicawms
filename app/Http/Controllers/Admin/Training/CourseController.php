<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\CourseRequest;
use App\Models\Training\Category;
use App\Models\Training\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::with('category')
            ->withCount('modules')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.training.courses.index', compact('courses'));
    }

    public function create()
    {
        return view('admin.training.courses.create', [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(CourseRequest $request)
    {
        $data = $this->payload($request);
        $data['published_at'] = $request->input('status') === 'published' ? now() : null;
        $data['created_by'] = Auth::id();

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $request->file('thumbnail')->store('training/thumbnails', 'public');
        }

        $course = Course::create($data);

        return redirect()->route('training.courses.content', $course->id)
            ->with('success', 'Course dibuat. Sekarang tambahkan modul & materi.');
    }

    public function edit(string $id)
    {
        $course = Course::findOrFail($id);

        return view('admin.training.courses.edit', [
            'course' => $course,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(CourseRequest $request, string $id)
    {
        $course = Course::findOrFail($id);
        $data = $this->payload($request);
        $data['published_at'] = $request->input('status') === 'published' ? ($course->published_at ?: now()) : null;
        $data['updated_by'] = Auth::id();

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail_path) {
                Storage::disk('public')->delete($course->thumbnail_path);
            }
            $data['thumbnail_path'] = $request->file('thumbnail')->store('training/thumbnails', 'public');
        }

        $course->update($data);

        return redirect()->route('training.courses.index')->with('success', 'Course diperbarui.');
    }

    public function destroy(string $id)
    {
        $course = Course::findOrFail($id);
        $course->deleted_by = Auth::id();
        $course->save();
        $course->delete();

        return redirect()->route('training.courses.index')->with('success', 'Course dihapus.');
    }

    public function publish(Request $request, string $id)
    {
        $course = Course::findOrFail($id);
        if ($course->status === 'published') {
            $course->update(['status' => 'draft', 'updated_by' => Auth::id()]);
            $msg = 'Course dikembalikan ke draft.';
        } else {
            $course->update([
                'status' => 'published',
                'published_at' => $course->published_at ?: now(),
                'updated_by' => Auth::id(),
            ]);
            $msg = 'Course dipublikasikan.';
        }

        return redirect()->route('training.courses.index')->with('success', $msg);
    }

    private function payload(CourseRequest $request): array
    {
        return [
            'title' => $request->string('title'),
            'category_id' => $request->input('category_id'),
            'description' => $request->input('description'),
            'status' => $request->input('status'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
