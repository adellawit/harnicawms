<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\City;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class BranchController extends Controller
{
    /**
     * Get accessible business unit IDs for the current user.
     */
    protected function getAccessibleBusinessUnitIds(): array
    {
        $user = auth('web')->user();

        // Superadmin can access all
        if ($user->is_super_admin) {
            return [];
        }

        return $user->getAccessibleBusinessUnitIdsForQuery();
    }

    public function indexView(Request $request)
    {
        $status = "";
        if ($request->filled('status')) {
            $status = $request['status'];
        }

        $isFilter = false;
        if (($status != "")) {
            $isFilter = true;
        }

        $user = auth('web')->user();
        $accessibleIds = $this->getAccessibleBusinessUnitIds();

        // Get companies with their branch children
        $query = BusinessUnit::whereNull('deleted_at')
            ->where('type_code', 'COMPANY');

        // Apply filter for non-superadmin users
        if (!$user->is_super_admin && !empty($accessibleIds)) {
            $userBu = $user->businessUnit;

            if ($userBu) {
                match($userBu->type_code) {
                    'HOLDING' => $query->whereIn('id', $this->getCompanyIdsByHolding($userBu->id)),
                    'COMPANY' => $query->where('id', $userBu->id),
                    'BRANCH' => $query->where('id', $userBu->parent_id),
                    default => $query->whereRaw('1=0'),
                };
            } else {
                $query->whereRaw('1=0');
            }
        }

        $parentCompanies = $query->with(['children' => function ($query) use ($accessibleIds, $user) {
            $query->where('type_code', 'BRANCH')
                ->whereNull('deleted_at');

            // Apply filter for non-superadmin users
            if (!$user->is_super_admin && !empty($accessibleIds)) {
                $query->whereIn('id', $accessibleIds);
            }

            $query->orderBy('name');
        }])->orderBy('name')->get();

        return view('admin.business.branch.index', compact('status', 'isFilter', 'parentCompanies'));
    }

    /**
     * Get company IDs under a holding.
     */
    protected function getCompanyIdsByHolding(string $holdingId): array
    {
        return BusinessUnit::where('parent_id', $holdingId)
            ->where('type_code', 'COMPANY')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();
    }

    public function indexData(Request $request)
    {
        $user = auth('web')->user();
        $accessibleIds = $this->getAccessibleBusinessUnitIds();

        $data = BusinessUnit::select(
            'business_units.id',
            'business_units.parent_id',
            'business_units.type_code',
            'business_units.code',
            'business_units.name',
            'business_units.brand_name',
            'business_units.is_active',
            'business_units.created_at',
            'business_units.deleted_at'
        )->from('master_data.business_units as business_units')
        ->where('business_units.type_code', 'BRANCH');

        // Apply filter for non-superadmin users
        if (!$user->is_super_admin && !empty($accessibleIds)) {
            $data = $data->whereIn('business_units.id', $accessibleIds);
        }

        if ($request['status'] == "active") {
        } else if ($request['status'] == "deleted") {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('business_units.name', 'ASC');

        $data->get();

        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value']) {
                    $query->where(function ($q) use ($search) {
                        $q->where('business_units.name', 'LIKE', "%{$search}%")
                            ->orWhere('business_units.code', 'LIKE', "%{$search}%")
                            ->orWhere('business_units.brand_name', 'LIKE', "%{$search}%")
                            ->orWhere('business_units.created_at', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        $user = auth('web')->user();
        $accessibleIds = $this->getAccessibleBusinessUnitIds();

        // Get companies as parent options
        $query = BusinessUnit::whereNull('deleted_at')
            ->where('type_code', 'COMPANY');

        // Apply filter for non-superadmin users
        if (!$user->is_super_admin && !empty($accessibleIds)) {
            $userBu = $user->businessUnit;

            if ($userBu) {
                match($userBu->type_code) {
                    'HOLDING' => $query->whereIn('id', $this->getCompanyIdsByHolding($userBu->id)),
                    'COMPANY' => $query->where('id', $userBu->id),
                    'BRANCH' => $query->where('id', $userBu->parent_id),
                    default => $query->whereRaw('1=0'),
                };
            } else {
                $query->whereRaw('1=0');
            }
        }

        $parentCompanies = $query->orderBy('name')->get();
        $provinces = Province::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return view('admin.business.branch.insert', compact('parentCompanies', 'provinces'));
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'parent_id' => 'nullable|exists:master_data.business_units,id',
            'code' => 'required|string|max:50|unique:master_data.business_units,code',
            'name' => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'npwp' => 'nullable|string|max:50',
            'nib' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'is_pos_active' => 'nullable|boolean',
            'is_inventory_active' => 'nullable|boolean',
            'tax_type' => 'nullable|in:inclusive,exclusive',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'service_charge_percentage' => 'nullable|numeric|min:0|max:100',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'opening_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ], [
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
            'name.required' => 'Name is required.',
            'email.email' => 'Invalid email format.',
        ]);

        BusinessUnit::create([
            'parent_id' => $request->parent_id,
            'type_code' => 'BRANCH',
            'code' => $request->code,
            'name' => $request->name,
            'brand_name' => $request->brand_name,
            'legal_name' => $request->legal_name,
            'npwp' => $request->npwp,
            'nib' => $request->nib,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'province' => $request->province,
            'postal_code' => $request->postal_code,
            'country' => $request->country,
            'is_pos_active' => $request->has('is_pos_active') ? true : false,
            'is_inventory_active' => $request->has('is_inventory_active') ? true : false,
            'tax_type' => $request->tax_type,
            'tax_percentage' => $request->tax_percentage,
            'service_charge_percentage' => $request->service_charge_percentage,
            'timezone' => $request->timezone,
            'currency' => $request->currency,
            'opening_date' => $request->opening_date,
            'is_active' => $request->has('is_active') ? true : false,
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('branch.index.view')->with('success', 'Successfully added branch');
    }

    public function editView(Request $request, $id)
    {
        $branch = BusinessUnit::where('id', $id)
            ->where('type_code', 'BRANCH')
            ->withTrashed()
            ->first();

        if (!$branch) {
            return redirect()->route('branch.index.view')->with('warning', 'Branch not found');
        }

        // Get companies as parent options
        $parentCompanies = BusinessUnit::whereNull('deleted_at')
            ->where('type_code', 'COMPANY')
            ->where('id', '!=', $id)
            ->orderBy('name')
            ->get();

        $selectedProvinceId = null;
        $selectedCityId = null;
        if ($branch->province) {
            $prov = Province::whereNull('deleted_at')->where('name', $branch->province)->first();
            if ($prov) {
                $selectedProvinceId = $prov->id;
                if ($branch->city) {
                    $city = City::whereNull('deleted_at')->where('province_id', $prov->id)->where('name', $branch->city)->first();
                    if ($city) {
                        $selectedCityId = $city->id;
                    }
                }
            }
        }

        $provinces = Province::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return view('admin.business.branch.edit', compact('branch', 'parentCompanies', 'provinces', 'selectedProvinceId', 'selectedCityId'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|string|exists:master_data.business_units,id',
            'parent_id' => 'nullable|exists:master_data.business_units,id',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('master_data.business_units', 'code')->ignore($request->id),
            ],
            'name' => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'npwp' => 'nullable|string|max:50',
            'nib' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'is_pos_active' => 'nullable|boolean',
            'is_inventory_active' => 'nullable|boolean',
            'tax_type' => 'nullable|in:inclusive,exclusive',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'service_charge_percentage' => 'nullable|numeric|min:0|max:100',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'opening_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ], [
            'id.required' => 'ID is required.',
            'id.exists' => 'Invalid branch ID.',
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
            'name.required' => 'Name is required.',
            'email.email' => 'Invalid email format.',
        ]);

        $branch = BusinessUnit::where('id', $request['id'])
            ->where('type_code', 'BRANCH')
            ->withTrashed()
            ->first();

        $branch->update([
            'parent_id' => $request->parent_id,
            'code' => $request->code,
            'name' => $request->name,
            'brand_name' => $request->brand_name,
            'legal_name' => $request->legal_name,
            'npwp' => $request->npwp,
            'nib' => $request->nib,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'province' => $request->province,
            'postal_code' => $request->postal_code,
            'country' => $request->country,
            'is_pos_active' => $request->has('is_pos_active') ? true : false,
            'is_inventory_active' => $request->has('is_inventory_active') ? true : false,
            'tax_type' => $request->tax_type,
            'tax_percentage' => $request->tax_percentage,
            'service_charge_percentage' => $request->service_charge_percentage,
            'timezone' => $request->timezone,
            'currency' => $request->currency,
            'opening_date' => $request->opening_date,
            'is_active' => $request->has('is_active') ? true : false,
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('branch.index.view')->with('success', 'Successfully updated branch');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'branch_id_deleted' => 'required|string|exists:master_data.business_units,id',
        ], [
            'branch_id_deleted.required' => 'Branch ID to be deleted is required.',
            'branch_id_deleted.string' => 'Branch ID must be a string.',
            'branch_id_deleted.exists' => 'Invalid branch ID or data not found.',
        ]);

        $branch = BusinessUnit::where('id', $request['branch_id_deleted'])
            ->first();

        $branch->updated_by = auth('web')->id();
        $branch->deleted_by = auth('web')->id();

        $branch->save();
        $branch->delete();

        return redirect()->route('branch.index.view')->with('success', 'Successfully deleted branch');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'branch_id_restored' => 'required|string|exists:master_data.business_units,id',
        ], [
            'branch_id_restored.required' => 'Branch ID to be restored is required.',
            'branch_id_restored.string' => 'Branch ID must be a string.',
            'branch_id_restored.exists' => 'Invalid branch ID or data not found.',
        ]);

        $branch = BusinessUnit::where('id', $request['branch_id_restored'])
            ->withTrashed()
            ->first();

        $branch->updated_by = auth('web')->id();
        $branch->deleted_by = null;

        $branch->save();
        $branch->restore();

        return redirect()->route('branch.index.view')->with('success', 'Successfully restored branch');
    }
}
