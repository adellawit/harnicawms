<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\MembershipPointConfiguration;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class MembershipConfigurationController extends Controller
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

    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        return view('admin.crm-membership.configuration.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $branchIds = $this->getAccessibleBranchIds();

        $data = MembershipPointConfiguration::query()
            ->select(
                'membership_point_configurations.id',
                'membership_point_configurations.branch_id',
                'membership_point_configurations.name',
                'membership_point_configurations.transaction_amount_step',
                'membership_point_configurations.points_per_step',
                'membership_point_configurations.redeem_value_per_point',
                'membership_point_configurations.is_default',
                'membership_point_configurations.deleted_at',
                'membership_point_configurations.created_at',
                'branches.name as branch_name'
            )
            ->leftJoin('master_data.business_units as branches', 'membership_point_configurations.branch_id', '=', 'branches.id');

        if (!empty($branchIds)) {
            $data = $data->whereIn('membership_point_configurations.branch_id', $branchIds);
        }

        if ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } elseif ($request->status !== 'active') {
            $data = $data->withTrashed();
        }

        $data = $data->orderByDesc('membership_point_configurations.created_at');

        return DataTables::of($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                $search = $request->get('search')['value'] ?? null;

                if (!$search) {
                    return;
                }

                $query->where(function ($q) use ($search) {
                    $q->where('membership_point_configurations.name', 'LIKE', "%{$search}%")
                        ->orWhere('membership_point_configurations.description', 'LIKE', "%{$search}%")
                        ->orWhere('branches.name', 'LIKE', "%{$search}%");
                });
            })
            ->toJson();
    }

    public function insertView()
    {
        $branchIds = $this->getAccessibleBranchIds();
        $branches = !empty($branchIds)
            ? BusinessUnit::whereIn('id', $branchIds)->orderBy('name')->get(['id', 'code', 'name'])
            : collect();
        $defaultBranchId = $branches->count() === 1
            ? $branches->first()?->id
            : $this->getBranchId();

        return view('admin.crm-membership.configuration.insert', compact('branches', 'defaultBranchId'));
    }

    public function insertData(Request $request)
    {
        $branchIds = $this->getAccessibleBranchIds();
        $branchId = $request->branch_id ?: $this->getBranchId();
        if (!empty($branchIds) && $branchId && !in_array($branchId, $branchIds)) {
            abort(403, 'Unauthorized branch.');
        }

        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('master_data.business_units', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(MembershipPointConfiguration::class, 'name')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at'),
            ],
            'transaction_amount_step' => 'required|integer|min:1',
            'points_per_step' => 'required|integer|min:1',
            'redeem_value_per_point' => 'required|integer|min:1',
            'is_default' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if (($validated['is_default'] ?? false) === true) {
            MembershipPointConfiguration::where('branch_id', $branchId)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        MembershipPointConfiguration::create([
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'transaction_amount_step' => $validated['transaction_amount_step'],
            'points_per_step' => $validated['points_per_step'],
            'redeem_value_per_point' => $validated['redeem_value_per_point'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'description' => $validated['description'] ?? null,
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('crm.membership-configuration.index.view')
            ->with('success', 'Successfully added membership configuration');
    }

    public function editView(string $id)
    {
        $configuration = MembershipPointConfiguration::withTrashed()->find($id);
        $branchIds = $this->getAccessibleBranchIds();

        if (!$configuration) {
            return redirect()->route('crm.membership-configuration.index.view')
                ->with('warning', 'Membership configuration not found');
        }

        if (!empty($branchIds) && $configuration->branch_id && !in_array($configuration->branch_id, $branchIds)) {
            abort(403, 'Unauthorized branch.');
        }

        $branches = !empty($branchIds)
            ? BusinessUnit::whereIn('id', $branchIds)->orderBy('name')->get(['id', 'code', 'name'])
            : collect();

        return view('admin.crm-membership.configuration.edit', compact('configuration', 'branches'));
    }

    public function editData(Request $request)
    {
        $configuration = MembershipPointConfiguration::withTrashed()->findOrFail($request->id);
        $branchIds = $this->getAccessibleBranchIds();
        $branchId = $request->branch_id ?: $configuration->branch_id;

        if (!empty($branchIds) && $branchId && !in_array($branchId, $branchIds)) {
            abort(403, 'Unauthorized branch.');
        }

        $validated = $request->validate([
            'id' => ['required', 'uuid', Rule::exists(MembershipPointConfiguration::class, 'id')],
            'branch_id' => ['required', Rule::exists('master_data.business_units', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(MembershipPointConfiguration::class, 'name')
                    ->where('branch_id', $branchId)
                    ->ignore($request->id)
                    ->whereNull('deleted_at'),
            ],
            'transaction_amount_step' => 'required|integer|min:1',
            'points_per_step' => 'required|integer|min:1',
            'redeem_value_per_point' => 'required|integer|min:1',
            'is_default' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if (($validated['is_default'] ?? false) === true) {
            MembershipPointConfiguration::where('id', '!=', $configuration->id)
                ->where('branch_id', $branchId)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $configuration->update([
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'transaction_amount_step' => $validated['transaction_amount_step'],
            'points_per_step' => $validated['points_per_step'],
            'redeem_value_per_point' => $validated['redeem_value_per_point'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'description' => $validated['description'] ?? null,
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('crm.membership-configuration.index.view')
            ->with('success', 'Successfully updated membership configuration');
    }

    public function deleteData(Request $request)
    {
        $validated = $request->validate([
            'membership_configuration_id_deleted' => ['required', 'uuid', Rule::exists(MembershipPointConfiguration::class, 'id')],
        ]);

        $configuration = MembershipPointConfiguration::findOrFail($validated['membership_configuration_id_deleted']);
        $branchIds = $this->getAccessibleBranchIds();
        if (!empty($branchIds) && $configuration->branch_id && !in_array($configuration->branch_id, $branchIds)) {
            abort(403, 'Unauthorized branch.');
        }

        $configuration->update([
            'updated_by' => auth('web')->id(),
            'deleted_by' => auth('web')->id(),
        ]);
        $configuration->delete();

        return redirect()->route('crm.membership-configuration.index.view')
            ->with('success', 'Successfully deleted membership configuration');
    }

    public function restoreData(Request $request)
    {
        $validated = $request->validate([
            'membership_configuration_id_restored' => ['required', 'uuid', Rule::exists(MembershipPointConfiguration::class, 'id')],
        ]);

        $configuration = MembershipPointConfiguration::withTrashed()->findOrFail($validated['membership_configuration_id_restored']);
        $branchIds = $this->getAccessibleBranchIds();
        if (!empty($branchIds) && $configuration->branch_id && !in_array($configuration->branch_id, $branchIds)) {
            abort(403, 'Unauthorized branch.');
        }

        $configuration->update([
            'updated_by' => auth('web')->id(),
            'deleted_by' => null,
        ]);
        $configuration->restore();

        return redirect()->route('crm.membership-configuration.index.view')
            ->with('success', 'Successfully restored membership configuration');
    }
}
