<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\City;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class CompanyController extends Controller
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

        // Get parent companies (holdings) with their company children
        $query = BusinessUnit::whereNull('deleted_at')
            ->where('type_code', 'HOLDING');

        // Apply filter for non-superadmin users
        if (!$user->is_super_admin && !empty($accessibleIds)) {
            $userBu = $user->businessUnit;

            if ($userBu) {
                $holdingId = match ($userBu->type_code) {
                    'HOLDING' => $userBu->id,
                    'COMPANY' => $userBu->parent_id,
                    'BRANCH' => $userBu->parent?->parent_id,
                    default => null,
                };

                if ($holdingId) {
                    $query->where('id', $holdingId);
                } else {
                    $query->whereRaw('1=0');
                }
            } else {
                $query->whereRaw('1=0');
            }
        }

        $parentCompanies = $query->with(['children' => function ($query) use ($accessibleIds, $user) {
            $query->where('type_code', 'COMPANY')
                ->whereNull('deleted_at');

            // Apply filter for non-superadmin users based on their type
            if (!$user->is_super_admin && !empty($accessibleIds)) {
                $userBu = $user->businessUnit;

                if ($userBu) {
                    // For HOLDING users: show all companies under their holding
                    // For COMPANY users: show all companies under their holding
                    // For BRANCH users: only show their parent company
                    if ($userBu->type_code === 'BRANCH') {
                        $query->where('id', $userBu->parent_id);
                    }
                    // HOLDING and COMPANY users can see all companies under the holding
                    // No additional filter needed as parent query already filters by holding
                }
            }

            $query->orderBy('name');
        }])->orderBy('name')->get();

        return view('admin.business.company.index', compact('status', 'isFilter', 'parentCompanies'));
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
        ->where('business_units.type_code', 'COMPANY');

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

        /** @var \Illuminate\Database\Eloquent\Builder $data */
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

        // Get holdings as parent options
        $query = BusinessUnit::whereNull('deleted_at')
            ->where('type_code', 'HOLDING');

        if (!$user->is_super_admin && !empty($accessibleIds)) {
            $userBu = $user->businessUnit;

            if ($userBu) {
                $holdingId = match ($userBu->type_code) {
                    'HOLDING' => $userBu->id,
                    'COMPANY' => $userBu->parent_id,
                    'BRANCH' => $userBu->parent?->parent_id,
                    default => null,
                };

                if ($holdingId) {
                    $query->where('id', $holdingId);
                } else {
                    $query->whereRaw('1=0');
                }
            } else {
                $query->whereRaw('1=0');
            }
        }

        $parentHoldings = $query->orderBy('name')->get();
        $provinces = Province::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return view('admin.business.company.insert', compact('parentHoldings', 'provinces'));
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
            'type_code' => 'COMPANY',
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

        return redirect()->route('company.index.view')->with('success', 'Successfully added company');
    }

    public function editView(Request $request, $id)
    {
        $company = BusinessUnit::where('id', $id)
            ->where('type_code', 'COMPANY')
            ->withTrashed()
            ->first();

        if (!$company) {
            return redirect()->route('company.index.view')->with('warning', 'Company not found');
        }

        // Get holdings as parent options
        $parentHoldings = BusinessUnit::whereNull('deleted_at')
            ->where('type_code', 'HOLDING')
            ->where('id', '!=', $id)
            ->orderBy('name')
            ->get();

        $selectedProvinceId = null;
        $selectedCityId = null;
        if ($company->province) {
            $prov = Province::whereNull('deleted_at')->where('name', $company->province)->first();
            if ($prov) {
                $selectedProvinceId = $prov->id;
                if ($company->city) {
                    $city = City::whereNull('deleted_at')->where('province_id', $prov->id)->where('name', $company->city)->first();
                    if ($city) {
                        $selectedCityId = $city->id;
                    }
                }
            }
        }

        $provinces = Province::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return view('admin.business.company.edit', compact('company', 'parentHoldings', 'provinces', 'selectedProvinceId', 'selectedCityId'));
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
            'id.exists' => 'Invalid company ID.',
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
            'name.required' => 'Name is required.',
            'email.email' => 'Invalid email format.',
        ]);

        $company = BusinessUnit::where('id', $request['id'])
            ->where('type_code', 'COMPANY')
            ->withTrashed()
            ->first();

        $company->update([
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

        return redirect()->route('company.index.view')->with('success', 'Successfully updated company');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'company_id_deleted' => 'required|string|exists:master_data.business_units,id',
        ], [
            'company_id_deleted.required' => 'Company ID to be deleted is required.',
            'company_id_deleted.string' => 'Company ID must be a string.',
            'company_id_deleted.exists' => 'Invalid company ID or data not found.',
        ]);

        $company = BusinessUnit::where('id', $request['company_id_deleted'])
            ->first();

        $company->updated_by = auth('web')->id();
        $company->deleted_by = auth('web')->id();

        $company->save();
        $company->delete();

        return redirect()->route('company.index.view')->with('success', 'Successfully deleted company');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'company_id_restored' => 'required|string|exists:master_data.business_units,id',
        ], [
            'company_id_restored.required' => 'Company ID to be restored is required.',
            'company_id_restored.string' => 'Company ID must be a string.',
            'company_id_restored.exists' => 'Invalid company ID or data not found.',
        ]);

        $company = BusinessUnit::where('id', $request['company_id_restored'])
            ->withTrashed()
            ->first();

        $company->updated_by = auth('web')->id();
        $company->deleted_by = null;

        $company->save();
        $company->restore();

        return redirect()->route('company.index.view')->with('success', 'Successfully restored company');
    }
}
