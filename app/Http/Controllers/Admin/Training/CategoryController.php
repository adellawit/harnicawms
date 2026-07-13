<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\CategoryRequest;
use App\Models\Training\Category;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('courses')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.training.categories.index', compact('categories'));
    }

    public function store(CategoryRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = Auth::id();

        Category::create($data);

        return redirect()->route('training.categories.index')
            ->with('success', 'Kategori ditambahkan.');
    }

    public function update(CategoryRequest $request, string $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['updated_by'] = Auth::id();

        $category->update($data);

        return redirect()->route('training.categories.index')
            ->with('success', 'Kategori diperbarui.');
    }

    public function destroy(string $id)
    {
        $category = Category::withCount('courses')->findOrFail($id);
        if ($category->courses_count > 0) {
            return redirect()->route('training.categories.index')
                ->with('error', 'Kategori dipakai oleh course dan tidak bisa dihapus.');
        }
        $category->deleted_by = Auth::id();
        $category->save();
        $category->delete();

        return redirect()->route('training.categories.index')
            ->with('success', 'Kategori dihapus.');
    }
}
