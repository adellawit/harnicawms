<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class ProductUnitController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        return view('admin.product.unit.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $data = ProductUnit::select(
            'product_units.id',
            'product_units.name',
            'product_units.code',
            'product_units.symbol',
            'product_units.created_at',
            'product_units.deleted_at'
        )->from('product.product_units as product_units');

        if ($branchId) {
            $data = $data->where(function ($query) use ($branchId) {
                $query->whereNull('product_units.branch_id')
                    ->orWhere('product_units.branch_id', $branchId);
            });
        }

        if ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } elseif ($request->status !== 'active') {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('product_units.name', 'ASC');

        /** @var \Illuminate\Database\Eloquent\Builder $data */
        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('product_units.name', 'LIKE', "%{$search}%")
                            ->orWhere('product_units.code', 'LIKE', "%{$search}%")
                            ->orWhere('product_units.symbol', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        return view('admin.product.unit.insert');
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:product.product_units,code',
            'symbol' => 'nullable|string|max:20',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Unit name is required.',
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
        ]);

        $user = auth('web')->user();

        ProductUnit::create([
            'company_id' => $user->getCompanyIdForProduct(),
            'branch_id' => $user->current_business_unit_id,
            'name' => $request->name,
            'code' => $request->code,
            'symbol' => $request->symbol,
            'description' => $request->description,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.unit.index.view')->with('success', 'Unit added successfully');
    }

    public function quickStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:product.product_units,code',
            'symbol' => 'nullable|string|max:20',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Unit name is required.',
            'code.required' => 'Unit code is required.',
            'code.unique' => 'Unit code already exists.',
        ]);

        $user = auth('web')->user();

        $unit = ProductUnit::create([
            'company_id' => $user->getCompanyIdForProduct(),
            'branch_id' => $user->current_business_unit_id,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'symbol' => $validated['symbol'] ?? null,
            'description' => $validated['description'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $label = $unit->symbol
            ? "{$unit->name} ({$unit->symbol})"
            : $unit->name;

        return response()->json([
            'success' => true,
            'message' => 'Unit added successfully.',
            'data' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'symbol' => $unit->symbol,
                'label' => $label,
            ],
        ]);
    }

    public function editView(Request $request, $id)
    {
        $unit = ProductUnit::where('id', $id)->withTrashed()->firstOrFail();

        return view('admin.product.unit.edit', compact('unit'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product.product_units,id',
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('product.product_units', 'code')->ignore($request->id)],
            'symbol' => 'nullable|string|max:20',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Unit name is required.',
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
        ]);

        $unit = ProductUnit::where('id', $request->id)->withTrashed()->firstOrFail();
        $unit->update([
            'name' => $request->name,
            'code' => $request->code,
            'symbol' => $request->symbol,
            'description' => $request->description,
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('product.unit.index.view')->with('success', 'Unit updated successfully');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'unit_id_deleted' => 'required|exists:product.product_units,id',
        ]);

        $unit = ProductUnit::findOrFail($request->unit_id_deleted);
        $unit->updated_by = auth('web')->id();
        $unit->deleted_by = auth('web')->id();
        $unit->save();
        $unit->delete();

        return redirect()->route('product.unit.index.view')->with('success', 'Unit deleted successfully');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'unit_id_restored' => 'required|exists:product.product_units,id',
        ]);

        $unit = ProductUnit::withTrashed()->findOrFail($request->unit_id_restored);
        $unit->updated_by = auth('web')->id();
        $unit->deleted_by = null;
        $unit->save();
        $unit->restore();

        return redirect()->route('product.unit.index.view')->with('success', 'Unit restored successfully');
    }
}
