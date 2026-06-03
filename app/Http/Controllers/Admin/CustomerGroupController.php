<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\CustomerGroup;
use App\Models\ProductPriceList;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class CustomerGroupController extends Controller
{
    protected function getAccessibleBranchIds(): array
    {
        $user = auth('web')->user();
        if (!$user || !$user->current_business_unit_id) {
            return [];
        }
        if ($user->is_super_admin) {
            return BusinessUnit::where('type_code', 'BRANCH')
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();
        }
        $accessibleIds = $user->getAccessibleBusinessUnitIdsForQuery();
        if (empty($accessibleIds)) {
            return [];
        }
        return BusinessUnit::whereIn('id', $accessibleIds)
            ->where('type_code', 'BRANCH')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();
    }

    protected function getBranchId(): ?string
    {
        return auth('web')->user()->current_business_unit_id;
    }

    protected function getCompanyId(): ?string
    {
        return auth('web')->user()->getCompanyIdForProduct();
    }

    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';
        $branchIds = $this->getAccessibleBranchIds();
        $branches = !empty($branchIds)
            ? BusinessUnit::whereIn('id', $branchIds)->orderBy('name')->get(['id', 'code', 'name'])
            : collect();

        return view('admin.customer.group.index', compact('status', 'isFilter', 'branches'));
    }

    public function indexData(Request $request)
    {
        $branchIds = $this->getAccessibleBranchIds();

        $data = CustomerGroup::query()
            ->select(
                'customer.customer_groups.id',
                'customer.customer_groups.branch_id',
                'customer.customer_groups.code',
                'customer.customer_groups.name',
                'customer.customer_groups.default_discount',
                'customer.customer_groups.allow_credit',
                'customer.customer_groups.credit_limit',
                'customer.customer_groups.payment_term_days',
                'customer.customer_groups.earn_point',
                'customer.customer_groups.point_multiplier',
                'customer.customer_groups.is_active',
                'customer.customer_groups.created_at',
                'customer.customer_groups.deleted_at',
                'pl.name as price_list_name',
                'bu.name as branch_name'
            )
            ->leftJoin('product.product_price_lists as pl', 'customer.customer_groups.price_list_id', '=', 'pl.id')
            ->leftJoin('master_data.business_units as bu', 'customer.customer_groups.branch_id', '=', 'bu.id');

        if (!empty($branchIds)) {
            $data = $data->whereIn('customer.customer_groups.branch_id', $branchIds);
        }

        if ($request->status === 'active') {
            // default - only non-deleted
        } elseif ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('customer.customer_groups.sort_order')
            ->orderBy('customer.customer_groups.name');

        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('customer.customer_groups.name', 'ilike', "%{$search}%")
                            ->orWhere('customer.customer_groups.code', 'ilike', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        $branchIds = $this->getAccessibleBranchIds();
        $branches = !empty($branchIds)
            ? BusinessUnit::whereIn('id', $branchIds)->orderBy('name')->get(['id', 'code', 'name'])
            : collect();
        $defaultBranchId = $branches->count() === 1
            ? $branches->first()?->id
            : $this->getBranchId();
        $companyId = $this->getCompanyId();
        $branchIdForPriceList = $defaultBranchId ?? $this->getBranchId();

        $priceLists = ProductPriceList::whereNull('deleted_at')
            ->where('is_active', true)
            ->when($companyId, fn ($q) => $q->where(function ($q2) use ($companyId) {
                $q2->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->when($branchIdForPriceList, fn ($q) => $q->where(function ($q2) use ($branchIdForPriceList) {
                $q2->where('branch_id', $branchIdForPriceList)->orWhereNull('branch_id');
            }))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('admin.customer.group.insert', array_merge(compact('branches', 'priceLists'), ['branchId' => $defaultBranchId]));
    }

    public function insertData(Request $request)
    {
        $branchIds = $this->getAccessibleBranchIds();
        $branchId = $request->branch_id ?: $this->getBranchId();

        if (!empty($branchIds) && $branchId && !in_array($branchId, $branchIds)) {
            abort(403, 'Unauthorized branch.');
        }

        $request->validate([
            'branch_id' => ['required', Rule::exists('master_data.business_units', 'id')],
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'price_list_id' => ['nullable', Rule::exists('product.product_price_lists', 'id')],
            'default_discount' => 'nullable|numeric|min:0|max:100',
            'allow_credit' => 'nullable|boolean',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_term_days' => 'nullable|integer|min:0',
            'earn_point' => 'nullable|boolean',
            'point_multiplier' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ], [
            'branch_id.required' => 'Branch is required.',
            'name.required' => 'Group name is required.',
        ]);

        $user = auth('web')->user();

        CustomerGroup::create([
            'branch_id' => $branchId,
            'code' => $request->code ?: null,
            'name' => $request->name,
            'description' => $request->description ?: null,
            'price_list_id' => $request->price_list_id ?: null,
            'default_discount' => $request->default_discount ?? 0,
            'allow_credit' => (bool) $request->allow_credit,
            'credit_limit' => $request->allow_credit && $request->filled('credit_limit') ? $request->credit_limit : null,
            'payment_term_days' => $request->payment_term_days ?? 0,
            'earn_point' => (bool) $request->earn_point,
            'point_multiplier' => $request->point_multiplier ?? 1,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active'),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('customer.group.index')->with('success', 'Customer group added successfully.');
    }

    public function editView(Request $request, $id)
    {
        $group = CustomerGroup::where('id', $id)->withTrashed()->with('branch')->firstOrFail();
        $branchIds = $this->getAccessibleBranchIds();

        if (!empty($branchIds) && !in_array($group->branch_id, $branchIds)) {
            abort(403, 'Unauthorized');
        }

        $companyId = $group->branch?->parent_id ?? $this->getCompanyId();
        $branchId = $group->branch_id;
        $priceLists = ProductPriceList::whereNull('deleted_at')
            ->where('is_active', true)
            ->when($companyId, fn ($q) => $q->where(function ($q2) use ($companyId) {
                $q2->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->when($branchId, fn ($q) => $q->where(function ($q2) use ($branchId) {
                $q2->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('admin.customer.group.edit', compact('group', 'priceLists'));
    }

    public function editData(Request $request)
    {
        $group = CustomerGroup::where('id', $request->id)->withTrashed()->firstOrFail();
        $branchIds = $this->getAccessibleBranchIds();

        if (!empty($branchIds) && !in_array($group->branch_id, $branchIds)) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'id' => ['required', Rule::exists(CustomerGroup::class, 'id')],
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'price_list_id' => ['nullable', Rule::exists('product.product_price_lists', 'id')],
            'default_discount' => 'nullable|numeric|min:0|max:100',
            'allow_credit' => 'nullable|boolean',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_term_days' => 'nullable|integer|min:0',
            'earn_point' => 'nullable|boolean',
            'point_multiplier' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ], [
            'name.required' => 'Group name is required.',
        ]);

        $group->update([
            'code' => $request->code ?: null,
            'name' => $request->name,
            'description' => $request->description ?: null,
            'price_list_id' => $request->price_list_id ?: null,
            'default_discount' => $request->default_discount ?? 0,
            'allow_credit' => (bool) $request->allow_credit,
            'credit_limit' => $request->allow_credit && $request->filled('credit_limit') ? $request->credit_limit : null,
            'payment_term_days' => $request->payment_term_days ?? 0,
            'earn_point' => (bool) $request->earn_point,
            'point_multiplier' => $request->point_multiplier ?? 1,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active'),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('customer.group.index')->with('success', 'Customer group updated successfully.');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'customer_group_id_deleted' => ['required', Rule::exists(CustomerGroup::class, 'id')],
        ]);

        $group = CustomerGroup::findOrFail($request->customer_group_id_deleted);
        $branchIds = $this->getAccessibleBranchIds();

        if (!empty($branchIds) && !in_array($group->branch_id, $branchIds)) {
            abort(403, 'Unauthorized');
        }

        $group->update([
            'deleted_by' => auth('web')->id(),
        ]);
        $group->delete();

        return redirect()->route('customer.group.index')->with('success', 'Customer group deleted successfully.');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'customer_group_id_restored' => ['required', Rule::exists(CustomerGroup::class, 'id')],
        ]);

        $group = CustomerGroup::withTrashed()->findOrFail($request->customer_group_id_restored);
        $branchIds = $this->getAccessibleBranchIds();

        if (!empty($branchIds) && !in_array($group->branch_id, $branchIds)) {
            abort(403, 'Unauthorized');
        }

        $group->update([
            'deleted_by' => null,
        ]);
        $group->restore();

        return redirect()->route('customer.group.index')->with('success', 'Customer group restored successfully.');
    }
}
