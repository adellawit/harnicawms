<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\City;
use App\Models\Province;
use App\Models\Warehouse;
use App\Models\WarehouseType;
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
        $companyId = $user->getCompanyIdForProduct();

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
        $scope = $request->get('scope', 'all');
        $isFilter = $status !== '' || $scope !== 'all';

        $user = auth('web')->user();
        $accessibleIds = $this->getAccessibleBusinessUnitIds();
        $companyId = $user->getCompanyIdForProduct();

        $parentCompanies = $this->companiesQuery()
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->with(['branches' => fn ($q) => $q->whereNull('deleted_at'), 'branch'])
            ->when(! $user->is_super_admin && ! empty($accessibleIds), function ($query) use ($accessibleIds, $companyId) {
                $query->where(function ($q) use ($accessibleIds) {
                    $q->whereIn('branch_id', $accessibleIds)
                        ->orWhereHas('branches', fn ($branch) => $branch->whereIn('master_data.business_units.id', $accessibleIds));
                })->when($companyId, function ($query) use ($companyId) {
                    $query->orWhere(function ($q) use ($companyId) {
                        $q->where('company_id', $companyId)
                            ->whereNull('branch_id');
                    });
                });
            })
            ->when($scope === 'distributor', fn ($query) => $query->whereNull('branch_id')->doesntHave('branches'))
            ->when($scope === 'branch', fn ($query) => $query->whereNotNull('branch_id'))
            ->when($scope === 'shared', fn ($query) => $query->whereNull('branch_id')->has('branches'))
            ->when($status === 'deleted', fn ($query) => $query->onlyTrashed())
            ->when($status !== 'active' && $status !== 'deleted', fn ($query) => $query->withTrashed())
            ->orderBy('name')
            ->get()
            ->groupBy('company_id');

        $parentCompanies->each(function ($company) use ($warehouses) {
            $company->setRelation('children', $warehouses->get($company->id, collect()));
        });

        return view('admin.business.warehouse.index', compact('status', 'scope', 'isFilter', 'parentCompanies'));
    }

    public function indexData(Request $request)
    {
        $user = auth('web')->user();
        $accessibleIds = $this->getAccessibleBusinessUnitIds();
        $companyId = $user->getCompanyIdForProduct();

        $data = Warehouse::query()
            ->select([
                'id',
                'company_id',
                'branch_id',
                'code',
                'name',
                'warehouse_type_code',
                'is_active',
                'is_inventory_active',
                'created_at',
                'deleted_at',
            ]);

        if (! $user->is_super_admin && ! empty($accessibleIds)) {
            $data = $data->where(function ($query) use ($accessibleIds, $companyId) {
                $query->whereIn('branch_id', $accessibleIds)
                    ->orWhereHas('branches', fn ($branch) => $branch->whereIn('master_data.business_units.id', $accessibleIds))
                    ->when($companyId, function ($q) use ($companyId) {
                        $q->orWhere(function ($inner) use ($companyId) {
                            $inner->where('company_id', $companyId)
                                ->whereNull('branch_id');
                        });
                    });
            });
        }

        if ($request['status'] === 'deleted') {
            $data = $data->onlyTrashed();
        } elseif ($request['status'] !== 'active') {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('name', 'ASC');

        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%")
                            ->orWhere('warehouse_type_code', 'LIKE', "%{$search}%");
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
            $warehouse = Warehouse::create(array_merge($data, [
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
        $warehouse = Warehouse::where('id', $id)
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

        $branches = $this->branchesForCompanies([$warehouse->company_id]);
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

        $warehouse = Warehouse::where('id', $request->id)
            ->withTrashed()
            ->firstOrFail();

        DB::transaction(function () use ($request, $data, $warehouse) {
            $warehouse->update(array_merge($data, [
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
            'warehouse_id_deleted' => 'required|string|exists:master_data.warehouses,id',
        ]);

        $warehouse = Warehouse::where('id', $request->warehouse_id_deleted)
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
            'warehouse_id_restored' => 'required|string|exists:master_data.warehouses,id',
        ]);

        $warehouse = Warehouse::where('id', $request->warehouse_id_restored)
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
                Rule::unique('master_data.warehouses', 'code')
                    ->where(fn ($query) => $query->where('company_id', $request->parent_id))
                    ->ignore($ignoreId),
            ],
            'name' => 'required|string|max:255',
            'brand_name' => 'nullable|exists:master_data.warehouse_types,code',
            'legal_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'uuid|exists:master_data.business_units,id',
            'default_branch_id' => 'nullable|uuid|exists:master_data.business_units,id',
        ], [
            'parent_id.required' => 'Distributor/Company wajib dipilih.',
            'code.required' => 'Kode gudang wajib diisi.',
            'code.unique' => 'Kode gudang sudah digunakan.',
            'name.required' => 'Nama gudang wajib diisi.',
        ]);

        $branchIds = array_values(array_unique(array_filter($request->input('branch_ids', []))));
        $defaultBranchId = $request->input('default_branch_id');
        $ownerBranchId = $defaultBranchId ?: ($branchIds[0] ?? null);

        return [
            'company_id' => $request->parent_id,
            'branch_id' => $ownerBranchId,
            'warehouse_type_code' => $request->brand_name ?: 'GENERAL',
            'code' => $request->code,
            'name' => $request->name,
            'short_name' => $request->brand_name,
            'legal_name' => $request->legal_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'province' => $request->province,
            'postal_code' => $request->postal_code,
            'country' => $request->country,
            'is_default' => $ownerBranchId && $ownerBranchId === $defaultBranchId,
        ];
    }

    /**
     * @param  list<string>  $branchIds
     */
    protected function syncLinkedBranches(Warehouse $warehouse, array $branchIds, ?string $defaultBranchId): void
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

    protected function resolveLocationIds($unit): array
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
        return WarehouseType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all();
    }
}
