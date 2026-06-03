<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
use App\Models\ParameterDetail;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class SupplierController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        return view('admin.master-data.supplier.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $data = Supplier::select(
            'master_data.suppliers.id',
            'master_data.suppliers.code',
            'master_data.suppliers.name',
            'master_data.suppliers.contact',
            'master_data.suppliers.phone',
            'master_data.suppliers.is_ppn',
            'master_data.suppliers.ppn_rate',
            'master_data.suppliers.is_active',
            'master_data.suppliers.created_at',
            'master_data.suppliers.deleted_at',
            'parameter_details.key as supplier_type_key',
            'parameter_details.value as supplier_type_value'
        )
            ->from('master_data.suppliers')
            ->leftJoin('public.parameter_details as parameter_details', 'master_data.suppliers.supplier_type_id', '=', 'parameter_details.id');

        if ($branchId) {
            $data = $data->where('master_data.suppliers.branch_id', $branchId);
        }

        if ($request->status === 'active') {
            // default
        } elseif ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('master_data.suppliers.created_at', 'DESC');

        /** @var \Illuminate\Database\Eloquent\Builder $data */
        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('master_data.suppliers.name', 'LIKE', "%{$search}%")
                            ->orWhere('master_data.suppliers.code', 'LIKE', "%{$search}%")
                            ->orWhere('master_data.suppliers.contact', 'LIKE', "%{$search}%")
                            ->orWhere('master_data.suppliers.phone', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        $parameterSupplier = Parameter::where('code', 'SUPPLIER')->first();
        $supplierTypes = $parameterSupplier
            ? ParameterDetail::where('parameter_id', $parameterSupplier->id)->whereNull('deleted_at')->orderBy('key')->get(['id', 'key', 'value'])
            : collect();

        $rawMaterialType = $supplierTypes->firstWhere('key', 'raw_material');
        $defaultSupplierTypeId = $rawMaterialType?->id;

        return view('admin.master-data.supplier.insert', compact('supplierTypes', 'defaultSupplierTypeId'));
    }

    public function insertData(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $supplierTypeId = $this->normalizeSupplierTypeId($request->supplier_type_id);

        // Fallback default Product untuk insert saat type tidak tersubmit
        if ($supplierTypeId === null) {
            $parameterSupplier = Parameter::where('code', 'SUPPLIER')->first();
            $rawMaterialType = $parameterSupplier
                ? ParameterDetail::where('parameter_id', $parameterSupplier->id)->where('key', 'raw_material')->whereNull('deleted_at')->first()
                : null;
            $supplierTypeId = $rawMaterialType?->id;
        }
        $request->merge(['supplier_type_id' => $supplierTypeId]);

        $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('master_data.suppliers', 'code')],
            'name' => 'required|string|max:200',
            'supplier_type_id' => ['nullable', Rule::exists('public.parameter_details', 'id')],
            'contact' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'is_ppn' => 'nullable|boolean',
            'ppn_rate' => 'nullable|numeric|min:0|max:100',
            'address' => 'nullable|string',
        ], [
            'code.required' => 'Code is required.',
            'name.required' => 'Name is required.',
            'supplier_type_id.exists' => 'Tipe supplier tidak valid.',
        ]);

        $user = auth('web')->user();
        $isPpn = (bool) $request->is_ppn;
        $ppnRate = $isPpn && $request->filled('ppn_rate') ? $request->ppn_rate : null;

        Supplier::create([
            'code' => $request->code,
            'name' => $request->name,
            'supplier_type_id' => $request->supplier_type_id ?: null,
            'company_id' => $user->getCompanyIdForProduct(),
            'branch_id' => $branchId,
            'contact' => $request->contact,
            'phone' => $request->phone,
            'email' => $request->email,
            'is_ppn' => $isPpn,
            'ppn_rate' => $ppnRate,
            'address' => $request->address,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.supplier.index.view')->with('success', 'Supplier added successfully');
    }

    public function editView(Request $request, $id)
    {
        $supplier = Supplier::where('id', $id)->withTrashed()->firstOrFail();
        $branchId = auth('web')->user()->current_business_unit_id;

        if ($branchId && $supplier->branch_id !== $branchId) {
            abort(403, 'Unauthorized');
        }

        $parameterSupplier = Parameter::where('code', 'SUPPLIER')->first();
        $supplierTypes = $parameterSupplier
            ? ParameterDetail::where('parameter_id', $parameterSupplier->id)->whereNull('deleted_at')->orderBy('key')->get(['id', 'key', 'value'])
            : collect();

        return view('admin.master-data.supplier.edit', compact('supplier', 'supplierTypes'));
    }

    public function editData(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $supplierTypeId = $this->normalizeSupplierTypeId($request->supplier_type_id);
        $supplier = Supplier::where('id', $request->id)->withTrashed()->firstOrFail();
        // Jaga nilai existing jika type tidak tersubmit
        if ($supplierTypeId === null && $supplier->supplier_type_id) {
            $supplierTypeId = $supplier->supplier_type_id;
        }
        $request->merge(['supplier_type_id' => $supplierTypeId]);

        $request->validate([
            'id' => 'required|exists:master_data.suppliers,id',
            'code' => ['required', 'string', 'max:50', Rule::unique('master_data.suppliers', 'code')->ignore($request->id)],
            'name' => 'required|string|max:200',
            'supplier_type_id' => ['nullable', Rule::exists('public.parameter_details', 'id')],
            'contact' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'is_ppn' => 'nullable|boolean',
            'ppn_rate' => 'nullable|numeric|min:0|max:100',
            'address' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ], [
            'code.required' => 'Code is required.',
            'name.required' => 'Name is required.',
            'supplier_type_id.exists' => 'Tipe supplier tidak valid.',
        ]);

        if ($branchId && $supplier->branch_id !== $branchId) {
            abort(403, 'Unauthorized');
        }

        $supplier->update([
            'code' => $request->code,
            'name' => $request->name,
            'supplier_type_id' => $request->supplier_type_id ?: null,
            'contact' => $request->contact,
            'phone' => $request->phone,
            'email' => $request->email,
            'is_ppn' => (bool) $request->is_ppn,
            'ppn_rate' => $request->is_ppn && $request->filled('ppn_rate') ? $request->ppn_rate : null,
            'address' => $request->address,
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('product.supplier.index.view')->with('success', 'Supplier updated successfully');
    }

    public function deleteData(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $request->validate([
            'supplier_id_deleted' => 'required|exists:master_data.suppliers,id',
        ]);

        $supplier = Supplier::findOrFail($request->supplier_id_deleted);
        if ($branchId && $supplier->branch_id !== $branchId) {
            abort(403, 'Unauthorized');
        }

        $supplier->updated_by = auth('web')->id();
        $supplier->deleted_by = auth('web')->id();
        $supplier->save();
        $supplier->delete();

        return redirect()->route('product.supplier.index.view')->with('success', 'Supplier deleted successfully');
    }

    public function restoreData(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $request->validate([
            'supplier_id_restored' => 'required|exists:master_data.suppliers,id',
        ]);

        $supplier = Supplier::withTrashed()->findOrFail($request->supplier_id_restored);
        if ($branchId && $supplier->branch_id !== $branchId) {
            abort(403, 'Unauthorized');
        }

        $supplier->updated_by = auth('web')->id();
        $supplier->deleted_by = null;
        $supplier->save();
        $supplier->restore();

        return redirect()->route('product.supplier.index.view')->with('success', 'Supplier restored successfully');
    }

    private function normalizeSupplierTypeId(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }
        $value = is_string($value) ? trim($value) : $value;
        if ($value === '' || $value === '0' || $value === null) {
            return null;
        }
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $value)) {
            return null;
        }

        return (string) $value;
    }
}
