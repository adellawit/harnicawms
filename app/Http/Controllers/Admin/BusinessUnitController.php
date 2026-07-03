<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\City;
use App\Models\Province;
use App\Services\MasterData\BusinessUnitCodeService;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class BusinessUnitController extends Controller
{
    public function __construct(
        protected BusinessUnitCodeService $businessUnitCodeService
    ) {
    }
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

    /**
     * Filter query based on user's business unit scope.
     */
    protected function applyBusinessUnitFilter($query, $accessibleIds = [])
    {
        $user = auth('web')->user();

        // Superadmin can see all
        if ($user->is_super_admin || empty($accessibleIds)) {
            return $query;
        }

        return $query->whereIn('id', $accessibleIds);
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

        $accessibleIds = $this->getAccessibleBusinessUnitIds();
        $user = auth('web')->user();

        // Get parent business units based on user's scope
        $query = BusinessUnit::whereNull('deleted_at');

        // Apply business unit filter if not superadmin
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
                    $query->where('id', $holdingId)
                        ->with(['children' => function ($q) {
                            $q->whereNull('deleted_at')
                                ->orderBy('name')
                                ->with(['children' => function ($q2) {
                                    $q2->whereNull('deleted_at')
                                        ->orderBy('name');
                                }]);
                        }]);
                } else {
                    $query->whereRaw('1=0');
                }
            } else {
                $query->whereRaw('1=0');
            }
        } else {
            // Superadmin or no filter - get all holdings with children
            $query->whereNull('parent_id')
                ->with(['children' => function ($q) {
                    $q->whereNull('deleted_at')
                        ->orderBy('name')
                        ->with(['children' => function ($q2) {
                            $q2->whereNull('deleted_at')
                                ->orderBy('name');
                        }]);
                }]);
        }

        $parentBusinessUnits = $query->orderBy('name')->get();

        return view('admin.business.unit.index', compact('status', 'isFilter', 'parentBusinessUnits'));
    }

    public function indexData(Request $request)
    {
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
        )->from('master_data.business_units as business_units');

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
                            ->orWhere('business_units.type_code', 'LIKE', "%{$search}%")
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

        // Get parent business units for dropdown (only holdings/companies)
        $query = BusinessUnit::whereNull('deleted_at')
            ->whereIn('type_code', ['HOLDING', 'COMPANY']);

        // Apply filter for non-superadmin users
        if (!$user->is_super_admin && !empty($accessibleIds)) {
            $query->whereIn('id', $accessibleIds);
        }

        $parentBusinessUnits = $query->orderBy('type_code')->orderBy('name')->get();
        $provinces = Province::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return view('admin.business.unit.insert', compact('parentBusinessUnits', 'provinces'));
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'parent_id' => 'nullable|exists:master_data.business_units,id',
            'type_code' => 'required|in:HOLDING,COMPANY,BRANCH',
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
            'type_code.required' => 'Type is required.',
            'type_code.in' => 'Type must be Holding, Company, or Branch.',
            'name.required' => 'Name is required.',
            'email.email' => 'Invalid email format.',
        ]);

        $generatedCode = $this->businessUnitCodeService->generate(
            $request->type_code,
            $request->parent_id
        );

        BusinessUnit::create([
            'parent_id' => $request->parent_id,
            'type_code' => $request->type_code,
            'code' => $generatedCode,
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

        return redirect()->route('holding.index.view')->with('success', 'Successfully added business unit (Code: '.$generatedCode.')');
    }

    public function editView(Request $request, $id)
    {
        $businessUnit = BusinessUnit::where('id', $id)
            ->withTrashed()
            ->first();

        if (!$businessUnit) {
            return redirect()->route('holding.index.view')->with('warning', 'Business unit not found');
        }

        // Get parent business units for dropdown (only holdings/companies)
        // Exclude current unit and its descendants to prevent circular references
        $parentBusinessUnits = BusinessUnit::whereNull('deleted_at')
            ->where('id', '!=', $id)
            ->whereIn('type_code', ['HOLDING', 'COMPANY'])
            ->orderBy('type_code')
            ->orderBy('name')
            ->get();

        $selectedProvinceId = null;
        $selectedCityId = null;
        if ($businessUnit->province) {
            $prov = Province::whereNull('deleted_at')->where('name', $businessUnit->province)->first();
            if ($prov) {
                $selectedProvinceId = $prov->id;
                if ($businessUnit->city) {
                    $city = City::whereNull('deleted_at')->where('province_id', $prov->id)->where('name', $businessUnit->city)->first();
                    if ($city) {
                        $selectedCityId = $city->id;
                    }
                }
            }
        }

        $provinces = Province::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return view('admin.business.unit.edit', compact('businessUnit', 'parentBusinessUnits', 'provinces', 'selectedProvinceId', 'selectedCityId'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|string|exists:master_data.business_units,id',
            'parent_id' => 'nullable|exists:master_data.business_units,id',
            'type_code' => 'required|in:HOLDING,COMPANY,BRANCH',
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
            'id.exists' => 'Invalid business unit ID.',
            'type_code.required' => 'Type is required.',
            'type_code.in' => 'Type must be Holding, Company, or Branch.',
            'name.required' => 'Name is required.',
            'email.email' => 'Invalid email format.',
        ]);

        $businessUnit = BusinessUnit::where('id', $request['id'])
            ->withTrashed()
            ->first();

        $businessUnit->update([
            'parent_id' => $request->parent_id,
            'type_code' => $request->type_code,
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

        return redirect()->route('holding.index.view')->with('success', 'Successfully updated business unit');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'business_unit_id_deleted' => 'required|string|exists:master_data.business_units,id',
        ], [
            'business_unit_id_deleted.required' => 'Business unit ID to be deleted is required.',
            'business_unit_id_deleted.string' => 'Business unit ID must be a string.',
            'business_unit_id_deleted.exists' => 'Invalid business unit ID or data not found.',
        ]);

        $businessUnit = BusinessUnit::where('id', $request['business_unit_id_deleted'])
            ->first();

        $businessUnit->updated_by = auth('web')->id();
        $businessUnit->deleted_by = auth('web')->id();

        $businessUnit->save();
        $businessUnit->delete();

        return redirect()->route('holding.index.view')->with('success', 'Successfully deleted business unit');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'business_unit_id_restored' => 'required|string|exists:master_data.business_units,id',
        ], [
            'business_unit_id_restored.required' => 'Business unit ID to be restored is required.',
            'business_unit_id_restored.string' => 'Business unit ID must be a string.',
            'business_unit_id_restored.exists' => 'Invalid business unit ID or data not found.',
        ]);

        $businessUnit = BusinessUnit::where('id', $request['business_unit_id_restored'])
            ->withTrashed()
            ->first();

        $businessUnit->updated_by = auth('web')->id();
        $businessUnit->deleted_by = null;

        $businessUnit->save();
        $businessUnit->restore();

        return redirect()->route('holding.index.view')->with('success', 'Successfully restored business unit');
    }
}
