<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\City;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class WarehouseController extends Controller
{
    protected function getAccessibleBusinessUnitIds(): array
    {
        $user = auth('web')->user();

        if ($user->is_super_admin) {
            return [];
        }

        return $user->getAccessibleBusinessUnitIdsForQuery();
    }

    protected function getCompanyIdsByHolding(string $holdingId): array
    {
        return BusinessUnit::where('parent_id', $holdingId)
            ->where('type_code', 'COMPANY')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();
    }

    protected function companiesQuery()
    {
        $user = auth('web')->user();
        $accessibleIds = $this->getAccessibleBusinessUnitIds();

        $query = BusinessUnit::whereNull('deleted_at')
            ->where('type_code', 'COMPANY');

        if (! $user->is_super_admin && ! empty($accessibleIds)) {
            $userBu = $user->businessUnit;

            if ($userBu) {
                match ($userBu->type_code) {
                    'HOLDING' => $query->whereIn('id', $this->getCompanyIdsByHolding($userBu->id)),
                    'COMPANY' => $query->where('id', $userBu->id),
                    'BRANCH' => $query->where('id', $userBu->parent_id),
                    default => $query->whereRaw('1=0'),
                };
            } else {
                $query->whereRaw('1=0');
            }
        }

        return $query;
    }

    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        $user = auth('web')->user();
        $accessibleIds = $this->getAccessibleBusinessUnitIds();

        $parentCompanies = $this->companiesQuery()
            ->with(['children' => function ($query) use ($accessibleIds, $user) {
                $query->where('type_code', 'WAREHOUSE')
                    ->whereNull('deleted_at')
                    ->with(['branches' => fn ($q) => $q->whereNull('deleted_at')])
                    ->orderBy('name');

                if (! $user->is_super_admin && ! empty($accessibleIds)) {
                    $query->whereIn('id', $accessibleIds);
                }
            }])
            ->orderBy('name')
            ->get();

        return view('admin.business.warehouse.index', compact('status', 'isFilter', 'parentCompanies'));
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
            'business_units.is_inventory_active',
            'business_units.created_at',
            'business_units.deleted_at'
        )->from('master_data.business_units as business_units')
            ->where('business_units.type_code', 'WAREHOUSE');

        if (! $user->is_super_admin && ! empty($accessibleIds)) {
            $data = $data->whereIn('business_units.id', $accessibleIds);
        }

        if ($request['status'] === 'deleted') {
            $data = $data->onlyTrashed();
        } elseif ($request['status'] !== 'active') {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('business_units.name', 'ASC')->get();

        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('business_units.name', 'LIKE', "%{$search}%")
                            ->orWhere('business_units.code', 'LIKE', "%{$search}%")
                            ->orWhere('business_units.brand_name', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView()
    {
        $parentCompanies = $this->companiesQuery()->orderBy('name')->get();
        $branches = $this->branchesForCompanies($parentCompanies->pluck('id')->all());
        $provinces = Province::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);
        $warehouseTypes = $this->warehouseTypes();

        return view('admin.business.warehouse.insert', compact('parentCompanies', 'branches', 'provinces', 'warehouseTypes'));
    }

    public function insertData(Request $request)
    {
        $data = $this->validateWarehouse($request);

        DB::transaction(function () use ($request, $data) {
            $warehouse = BusinessUnit::create(array_merge($data, [
                'type_code' => 'WAREHOUSE',
                'is_pos_active' => $request->has('is_pos_active'),
                'is_inventory_active' => $request->has('is_inventory_active'),
                'is_active' => $request->has('is_active'),
                'created_by' => auth('web')->id(),
                'updated_by' => auth('web')->id(),
            ]));

            $this->syncLinkedBranches($warehouse, $request->input('branch_ids', []), $request->input('default_branch_id'));
        });

        return redirect()->route('warehouse.index.view')->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function editView(string $id)
    {
        $warehouse = BusinessUnit::where('id', $id)
            ->where('type_code', 'WAREHOUSE')
            ->with(['branches'])
            ->withTrashed()
            ->first();

        if (! $warehouse) {
            return redirect()->route('warehouse.index.view')->with('warning', 'Gudang tidak ditemukan.');
        }

        $parentCompanies = BusinessUnit::whereNull('deleted_at')
            ->where('type_code', 'COMPANY')
            ->where('id', '!=', $id)
            ->orderBy('name')
            ->get();

        $branches = $this->branchesForCompanies([$warehouse->parent_id]);
        $linkedBranchIds = $warehouse->branches->pluck('id')->all();
        $defaultBranchId = $warehouse->branches->firstWhere('pivot.is_default', true)?->id;

        [$selectedProvinceId, $selectedCityId] = $this->resolveLocationIds($warehouse);
        $provinces = Province::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);
        $warehouseTypes = $this->warehouseTypes();

        return view('admin.business.warehouse.edit', compact(
            'warehouse',
            'parentCompanies',
            'branches',
            'linkedBranchIds',
            'defaultBranchId',
            'provinces',
            'selectedProvinceId',
            'selectedCityId',
            'warehouseTypes'
        ));
    }

    public function editData(Request $request)
    {
        $data = $this->validateWarehouse($request, $request->id);

        $warehouse = BusinessUnit::where('id', $request->id)
            ->where('type_code', 'WAREHOUSE')
            ->withTrashed()
            ->firstOrFail();

        DB::transaction(function () use ($request, $data, $warehouse) {
            $warehouse->update(array_merge($data, [
                'is_pos_active' => $request->has('is_pos_active'),
                'is_inventory_active' => $request->has('is_inventory_active'),
                'is_active' => $request->has('is_active'),
                'updated_by' => auth('web')->id(),
            ]));

            $this->syncLinkedBranches($warehouse, $request->input('branch_ids', []), $request->input('default_branch_id'));
        });

        return redirect()->route('warehouse.index.view')->with('success', 'Gudang berhasil diperbarui.');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'warehouse_id_deleted' => 'required|string|exists:master_data.business_units,id',
        ]);

        $warehouse = BusinessUnit::where('id', $request->warehouse_id_deleted)
            ->where('type_code', 'WAREHOUSE')
            ->firstOrFail();

        $warehouse->updated_by = auth('web')->id();
        $warehouse->deleted_by = auth('web')->id();
        $warehouse->save();
        $warehouse->delete();

        return redirect()->route('warehouse.index.view')->with('success', 'Gudang berhasil dihapus.');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'warehouse_id_restored' => 'required|string|exists:master_data.business_units,id',
        ]);

        $warehouse = BusinessUnit::where('id', $request->warehouse_id_restored)
            ->where('type_code', 'WAREHOUSE')
            ->withTrashed()
            ->firstOrFail();

        $warehouse->updated_by = auth('web')->id();
        $warehouse->deleted_by = null;
        $warehouse->save();
        $warehouse->restore();

        return redirect()->route('warehouse.index.view')->with('success', 'Gudang berhasil dipulihkan.');
    }

    protected function validateWarehouse(Request $request, ?string $ignoreId = null): array
    {
        $request->validate([
            'parent_id' => 'required|exists:master_data.business_units,id',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('master_data.business_units', 'code')->ignore($ignoreId),
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
            'tax_type' => 'nullable|in:inclusive,exclusive',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'service_charge_percentage' => 'nullable|numeric|min:0|max:100',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'opening_date' => 'nullable|date',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'uuid|exists:master_data.business_units,id',
            'default_branch_id' => 'nullable|uuid|exists:master_data.business_units,id',
        ], [
            'parent_id.required' => 'Distributor/Company wajib dipilih.',
            'code.required' => 'Kode gudang wajib diisi.',
            'code.unique' => 'Kode gudang sudah digunakan.',
            'name.required' => 'Nama gudang wajib diisi.',
        ]);

        return $request->only([
            'parent_id', 'code', 'name', 'brand_name', 'legal_name', 'npwp', 'nib',
            'email', 'phone', 'address', 'city', 'province', 'postal_code', 'country',
            'tax_type', 'tax_percentage', 'service_charge_percentage', 'timezone',
            'currency', 'opening_date',
        ]);
    }

    /**
     * @param  list<string>  $branchIds
     */
    protected function syncLinkedBranches(BusinessUnit $warehouse, array $branchIds, ?string $defaultBranchId): void
    {
        $branchIds = array_values(array_unique(array_filter($branchIds)));

        if ($defaultBranchId && ! in_array($defaultBranchId, $branchIds, true)) {
            $branchIds[] = $defaultBranchId;
        }

        $sync = [];
        foreach ($branchIds as $branchId) {
            $sync[$branchId] = ['is_default' => $defaultBranchId === $branchId];
        }

        $warehouse->branches()->sync($sync);
    }

    /**
     * @param  list<string>  $companyIds
     */
    protected function branchesForCompanies(array $companyIds)
    {
        if ($companyIds === []) {
            return collect();
        }

        return BusinessUnit::query()
            ->where('type_code', 'BRANCH')
            ->whereIn('parent_id', $companyIds)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'parent_id']);
    }

    protected function resolveLocationIds(BusinessUnit $unit): array
    {
        $selectedProvinceId = null;
        $selectedCityId = null;

        if ($unit->province) {
            $prov = Province::whereNull('deleted_at')->where('name', $unit->province)->first();
            if ($prov) {
                $selectedProvinceId = $prov->id;
                if ($unit->city) {
                    $city = City::whereNull('deleted_at')
                        ->where('province_id', $prov->id)
                        ->where('name', $unit->city)
                        ->first();
                    if ($city) {
                        $selectedCityId = $city->id;
                    }
                }
            }
        }

        return [$selectedProvinceId, $selectedCityId];
    }

    /**
     * @return array<string, string>
     */
    protected function warehouseTypes(): array
    {
        return [
            'WIP' => 'Gudang WIP (Bahan Baku & Proses)',
            'FG' => 'Gudang Barang Jadi',
            'GENERAL' => 'Gudang Umum',
            'TRANSIT' => 'Gudang Transit',
        ];
    }
}
