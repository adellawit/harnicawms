<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class ProductCategoryController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        return view('admin.product.category.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $data = ProductCategory::select(
            'product_categories.id',
            'product_categories.parent_id',
            'product_categories.name',
            'product_categories.code',
            'product_categories.sort_order',
            'product_categories.created_at',
            'product_categories.deleted_at'
        )->from('product.product_categories as product_categories')
            ->with('parent:id,name');

        if ($companyId) {
            $data = $data->where('product_categories.company_id', $companyId);
        }

        if ($request->status === 'active') {
            // default
        } elseif ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('product_categories.sort_order', 'ASC')
            ->orderBy('product_categories.created_at', 'DESC');

        /** @var \Illuminate\Database\Eloquent\Builder $data */
        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->addColumn('parent_name', fn ($row) => $row->parent?->name ?? '-')
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('product_categories.name', 'LIKE', "%{$search}%")
                            ->orWhere('product_categories.code', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $categories = ProductCategory::whereNull('deleted_at')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.product.category.insert', compact('categories'));
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'parent_id' => 'nullable|exists:product.product_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Category name is required.',
        ]);

        $user = auth('web')->user();

        ProductCategory::create([
            'company_id' => $user->getCompanyIdForProduct(),
            'branch_id' => $user->current_business_unit_id,
            'parent_id' => $request->parent_id ?: null,
            'name' => $request->name,
            'code' => $request->code ?: null,
            'sort_order' => $request->sort_order ?? 0,
            'description' => $request->description,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.category.index.view')->with('success', 'Category added successfully');
    }

    public function quickStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:product.product_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Category name is required.',
        ]);

        $user = auth('web')->user();

        $category = ProductCategory::create([
            'company_id' => $user->getCompanyIdForProduct(),
            'branch_id' => $user->current_business_unit_id,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'description' => $validated['description'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category added successfully.',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'label' => $category->name,
            ],
        ]);
    }

    public function editView(Request $request, $id)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $category = ProductCategory::where('id', $id)->withTrashed()->firstOrFail();
        $categories = ProductCategory::whereNull('deleted_at')
            ->where('id', '!=', $id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.product.category.edit', compact('category', 'categories'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product.product_categories,id',
            'parent_id' => 'nullable|exists:product.product_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Category name is required.',
        ]);

        $category = ProductCategory::where('id', $request->id)->withTrashed()->firstOrFail();
        $category->update([
            'parent_id' => $request->parent_id ?: null,
            'name' => $request->name,
            'code' => $request->code ?: null,
            'sort_order' => $request->sort_order ?? 0,
            'description' => $request->description,
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('product.category.index.view')->with('success', 'Category updated successfully');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'category_id_deleted' => 'required|exists:product.product_categories,id',
        ]);

        $category = ProductCategory::findOrFail($request->category_id_deleted);
        $category->updated_by = auth('web')->id();
        $category->deleted_by = auth('web')->id();
        $category->save();
        $category->delete();

        return redirect()->route('product.category.index.view')->with('success', 'Category deleted successfully');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'category_id_restored' => 'required|exists:product.product_categories,id',
        ]);

        $category = ProductCategory::withTrashed()->findOrFail($request->category_id_restored);
        $category->updated_by = auth('web')->id();
        $category->deleted_by = null;
        $category->save();
        $category->restore();

        return redirect()->route('product.category.index.view')->with('success', 'Category restored successfully');
    }
}
