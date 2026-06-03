<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductTag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class ProductTagController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        return view('admin.product.tag.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $data = ProductTag::select(
            'product_tags.id',
            'product_tags.name',
            'product_tags.code',
            'product_tags.type',
            'product_tags.color',
            'product_tags.sort_order',
            'product_tags.created_at',
            'product_tags.deleted_at'
        )->from('product.product_tags as product_tags');

        if ($branchId) {
            $data = $data->where('product_tags.branch_id', $branchId);
        }

        if ($request->status === 'active') {
            // default
        } elseif ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('product_tags.sort_order', 'ASC')
            ->orderBy('product_tags.created_at', 'DESC');

        /** @var \Illuminate\Database\Eloquent\Builder $data */
        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->addColumn('type_label', fn ($row) => ProductTag::typeOptions()[$row->type] ?? $row->type)
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('product_tags.name', 'LIKE', "%{$search}%")
                            ->orWhere('product_tags.code', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        return view('admin.product.tag.insert');
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'type' => 'required|in:general,best_seller,product_focus,featured',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'Name is required.',
        ]);

        $user = auth('web')->user();

        ProductTag::create([
            'company_id' => $user->getCompanyIdForProduct(),
            'branch_id' => $user->current_business_unit_id,
            'name' => $request->name,
            'code' => $request->code ?: null,
            'type' => $request->type,
            'color' => $request->color ?: null,
            'description' => $request->description,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.tag.index.view')->with('success', 'Tag added successfully');
    }

    public function editView(Request $request, $id)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $tag = ProductTag::where('id', $id)->withTrashed()->firstOrFail();
        if ($branchId && $tag->branch_id !== $branchId) {
            abort(403);
        }

        return view('admin.product.tag.edit', compact('tag'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product.product_tags,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'type' => 'required|in:general,best_seller,product_focus,featured',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'Name is required.',
        ]);

        $tag = ProductTag::where('id', $request->id)->withTrashed()->firstOrFail();
        $tag->update([
            'name' => $request->name,
            'code' => $request->code ?: null,
            'type' => $request->type,
            'color' => $request->color ?: null,
            'description' => $request->description,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('product.tag.index.view')->with('success', 'Tag updated successfully');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'tag_id_deleted' => 'required|exists:product.product_tags,id',
        ]);

        $tag = ProductTag::findOrFail($request->tag_id_deleted);
        $tag->updated_by = auth('web')->id();
        $tag->deleted_by = auth('web')->id();
        $tag->save();
        $tag->delete();

        return redirect()->route('product.tag.index.view')->with('success', 'Tag deleted successfully');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'tag_id_restored' => 'required|exists:product.product_tags,id',
        ]);

        $tag = ProductTag::withTrashed()->findOrFail($request->tag_id_restored);
        $tag->updated_by = auth('web')->id();
        $tag->deleted_by = null;
        $tag->save();
        $tag->restore();

        return redirect()->route('product.tag.index.view')->with('success', 'Tag restored successfully');
    }
}
