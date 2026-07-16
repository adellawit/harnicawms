<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\CategoryRequest;
use App\Models\Marketing\Category;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('assets')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.marketing.categories.index', compact('categories'));
    }

    public function store(CategoryRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = Auth::id();

        Category::create($data);

        return redirect()->route('marketing.categories.index')->with('success', 'Kategori ditambahkan.');
    }

    public function update(CategoryRequest $request, string $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['updated_by'] = Auth::id();

        $category->update($data);

        return redirect()->route('marketing.categories.index')->with('success', 'Kategori diperbarui.');
    }

    public function destroy(string $id)
    {
        $category = Category::withCount('assets')->findOrFail($id);
        if ($category->assets_count > 0) {
            return redirect()->route('marketing.categories.index')
                ->with('error', 'Kategori dipakai oleh aset dan tidak bisa dihapus.');
        }
        $category->deleted_by = Auth::id();
        $category->save();
        $category->delete();

        return redirect()->route('marketing.categories.index')->with('success', 'Kategori dihapus.');
    }
}
