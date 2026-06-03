<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductNature;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class ProductNatureController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        return view('admin.product.nature.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $data = ProductNature::select(
            'product_natures.id',
            'product_natures.parent_id',
            'product_natures.name',
            'product_natures.code',
            'product_natures.created_at',
            'product_natures.deleted_at'
        )->from('product.product_natures as product_natures');

        // Filter by company_id OR NULL (global/master data)
        if ($companyId) {
            $data = $data->where(function ($q) use ($companyId) {
                $q->where('product_natures.company_id', $companyId)
                    ->orWhereNull('product_natures.company_id');
            });
        }

        if ($request->status === 'active') {
            // default
        } elseif ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('product_natures.created_at', 'DESC');

        /** @var \Illuminate\Database\Eloquent\Builder $data */
        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('product_natures.name', 'LIKE', "%{$search}%")
                            ->orWhere('product_natures.code', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $natures = ProductNature::whereNull('deleted_at')
            ->when($companyId, function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.product.nature.insert', compact('natures'));
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'parent_id' => 'nullable|exists:product.product_natures,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:product.product_natures,code',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Product type name is required.',
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
        ]);

        $user = auth('web')->user();

        ProductNature::create([
            'company_id' => $user->getCompanyIdForProduct(),
            'branch_id' => $user->current_business_unit_id,
            'parent_id' => $request->parent_id ?: null,
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.nature.index.view')->with('success', 'Product type added successfully');
    }

    public function editView(Request $request, $id)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $nature = ProductNature::where('id', $id)->withTrashed()->firstOrFail();
        $natures = ProductNature::whereNull('deleted_at')
            ->where('id', '!=', $id)
            ->when($companyId, function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.product.nature.edit', compact('nature', 'natures'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product.product_natures,id',
            'parent_id' => 'nullable|exists:product.product_natures,id',
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', Rule::unique('product.product_natures', 'code')->ignore($request->id)],
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Product type name is required.',
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
        ]);

        $nature = ProductNature::where('id', $request->id)->withTrashed()->firstOrFail();
        $nature->update([
            'parent_id' => $request->parent_id ?: null,
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('product.nature.index.view')->with('success', 'Product type updated successfully');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'nature_id_deleted' => 'required|exists:product.product_natures,id',
        ]);

        $nature = ProductNature::findOrFail($request->nature_id_deleted);
        $nature->updated_by = auth('web')->id();
        $nature->deleted_by = auth('web')->id();
        $nature->save();
        $nature->delete();

        return redirect()->route('product.nature.index.view')->with('success', 'Product type deleted successfully');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'nature_id_restored' => 'required|exists:product.product_natures,id',
        ]);

        $nature = ProductNature::withTrashed()->findOrFail($request->nature_id_restored);
        $nature->updated_by = auth('web')->id();
        $nature->deleted_by = null;
        $nature->save();
        $nature->restore();

        return redirect()->route('product.nature.index.view')->with('success', 'Product type restored successfully');
    }
}
