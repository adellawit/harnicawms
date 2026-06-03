<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCollection;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ProductCollectionController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        return view('admin.product.collection.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $data = ProductCollection::select(
            'product_collections.id',
            'product_collections.parent_id',
            'product_collections.name',
            'product_collections.code',
            'product_collections.sort_order',
            'product_collections.created_at',
            'product_collections.deleted_at'
        )->from('product.product_collections as product_collections')
            ->with('parent:id,name');

        if ($companyId) {
            $data = $data->where('product_collections.company_id', $companyId);
        }

        if ($request->status === 'active') {
            // default
        } elseif ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('product_collections.sort_order', 'ASC')
            ->orderBy('product_collections.created_at', 'DESC');

        /** @var \Illuminate\Database\Eloquent\Builder $data */
        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->addColumn('parent_name', fn ($row) => $row->parent?->name ?? '-')
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('product_collections.name', 'LIKE', "%{$search}%")
                            ->orWhere('product_collections.code', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $collections = ProductCollection::whereNull('deleted_at')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.product.collection.insert', compact('collections'));
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'parent_id' => 'nullable|exists:product.product_collections,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'Collection name is required.',
        ]);

        $user = auth('web')->user();

        ProductCollection::create([
            'company_id' => $user->getCompanyIdForProduct(),
            'branch_id' => $user->current_business_unit_id,
            'parent_id' => $request->parent_id ?: null,
            'name' => $request->name,
            'code' => $request->code ?: null,
            'description' => $request->description,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.collection.index.view')->with('success', 'Collection added successfully');
    }

    public function editView(Request $request, $id)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $collection = ProductCollection::where('id', $id)->withTrashed()->firstOrFail();
        if ($companyId && $collection->company_id !== $companyId) {
            abort(403);
        }
        $collections = ProductCollection::whereNull('deleted_at')
            ->where('id', '!=', $id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.product.collection.edit', compact('collection', 'collections'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product.product_collections,id',
            'parent_id' => 'nullable|exists:product.product_collections,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'Collection name is required.',
        ]);

        $collection = ProductCollection::where('id', $request->id)->withTrashed()->firstOrFail();
        $collection->update([
            'parent_id' => $request->parent_id ?: null,
            'name' => $request->name,
            'code' => $request->code ?: null,
            'description' => $request->description,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('product.collection.index.view')->with('success', 'Collection updated successfully');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'collection_id_deleted' => 'required|exists:product.product_collections,id',
        ]);

        $collection = ProductCollection::findOrFail($request->collection_id_deleted);
        $collection->updated_by = auth('web')->id();
        $collection->deleted_by = auth('web')->id();
        $collection->save();
        $collection->delete();

        return redirect()->route('product.collection.index.view')->with('success', 'Collection deleted successfully');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'collection_id_restored' => 'required|exists:product.product_collections,id',
        ]);

        $collection = ProductCollection::withTrashed()->findOrFail($request->collection_id_restored);
        $collection->updated_by = auth('web')->id();
        $collection->deleted_by = null;
        $collection->save();
        $collection->restore();

        return redirect()->route('product.collection.index.view')->with('success', 'Collection restored successfully');
    }
}
