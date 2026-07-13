<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class CustomerController extends Controller
{
    protected function getAccessibleGroupIds(): array
    {
        $user = auth('web')->user();
        if (!$user || !$user->current_business_unit_id) {
            return [];
        }
        $branchIds = [];
        if ($user->is_super_admin) {
            $branchIds = BusinessUnit::where('type_code', 'BRANCH')->whereNull('deleted_at')->pluck('id')->toArray();
        } else {
            $accessibleIds = $user->getAccessibleBusinessUnitIdsForQuery();
            if (!empty($accessibleIds)) {
                $branchIds = BusinessUnit::whereIn('id', $accessibleIds)
                    ->where('type_code', 'BRANCH')
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->toArray();
            }
        }
        if (empty($branchIds)) {
            return [];
        }
        return CustomerGroup::whereIn('branch_id', $branchIds)->whereNull('deleted_at')->pluck('id')->toArray();
    }

    protected function handleAttachments(Request $request, ?array $existing = null): array
    {
        $attachments = $existing ?? [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file->isValid()) {
                    $filename = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
                    $path = $file->storeAs('customers/attachments', $filename, 'public');
                    $attachments[] = [
                        'path' => $path,
                        'name' => $file->getClientOriginalName(),
                    ];
                }
            }
        }
        return $attachments;
    }

    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        return view('admin.customer.list.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $groupIds = $this->getAccessibleGroupIds();

        $data = Customer::query()
            ->select(
                'customer.customers.id',
                'customer.customers.customer_group_id',
                'customer.customers.code',
                'customer.customers.name',
                'customer.customers.contact_person',
                'customer.customers.email',
                'customer.customers.phone',
                'customer.customers.customer_type',
                'customer.customers.has_app_access',
                'customer.customers.is_active',
                'customer.customers.created_at',
                'customer.customers.deleted_at',
                'cg.name as group_name',
                'bu.name as branch_name',
                'pa.id as partner_agent_id',
                'pa.code as partner_agent_code',
                'pr.id as partner_reseller_id',
                'pr.code as partner_reseller_code',
            )
            ->join('customer.customer_groups as cg', 'customer.customers.customer_group_id', '=', 'cg.id')
            ->leftJoin('master_data.business_units as bu', 'cg.branch_id', '=', 'bu.id')
            ->leftJoin('partner.agents as pa', function ($join) {
                $join->on('customer.customers.id', '=', 'pa.customer_id')
                    ->whereNull('pa.deleted_at');
            })
            ->leftJoin('partner.resellers as pr', function ($join) {
                $join->on('customer.customers.id', '=', 'pr.customer_id')
                    ->whereNull('pr.deleted_at');
            });

        if (!empty($groupIds)) {
            $data = $data->whereIn('customer.customers.customer_group_id', $groupIds);
        }

        if ($request->status === 'active') {
            // default
        } elseif ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('customer.customers.created_at', 'desc');

        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->addColumn('partner_role', function ($row) {
                if ($row->partner_agent_id) {
                    return 'agent';
                }

                if ($row->partner_reseller_id) {
                    return 'reseller';
                }

                if ($row->customer_type === Customer::TYPE_PARTNER_LEAD) {
                    return 'partner_lead';
                }

                return null;
            })
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('customer.customers.name', 'ilike', "%{$search}%")
                            ->orWhere('customer.customers.code', 'ilike', "%{$search}%")
                            ->orWhere('customer.customers.contact_person', 'ilike', "%{$search}%")
                            ->orWhere('customer.customers.email', 'ilike', "%{$search}%")
                            ->orWhere('customer.customers.phone', 'ilike', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        $groupIds = $this->getAccessibleGroupIds();
        $customerGroups = !empty($groupIds)
            ? CustomerGroup::with('branch')
                ->whereIn('id', $groupIds)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get()
            : collect();

        return view('admin.customer.list.insert', compact('customerGroups'));
    }

    public function insertData(Request $request)
    {
        $groupIds = $this->getAccessibleGroupIds();
        $groupId = $request->customer_group_id;

        if (!empty($groupIds) && (!in_array($groupId, $groupIds))) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'customer_group_id' => ['required', Rule::exists(CustomerGroup::class, 'id')],
            'code' => ['required', 'string', 'max:50', Rule::unique(Customer::class, 'code')->where('customer_group_id', $groupId)->whereNull('deleted_at')],
            'name' => 'required|string|max:200',
            'tax_number' => 'nullable|string|max:50',
            'tax_name' => 'nullable|string|max:200',
            'tax_address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'lat' => 'nullable|numeric|between:-90,90',
            'long' => 'nullable|numeric|between:-180,180',
            'identity_type' => 'nullable|string|max:30',
            'identity_number' => 'nullable|string|max:50',
            'username' => ['nullable', 'string', 'max:100', Rule::unique(Customer::class, 'username')->whereNull('deleted_at')],
            'password' => 'nullable|string|min:6|required_if:has_app_access,1',
            'has_app_access' => 'nullable|boolean',
            'customer_type' => 'nullable|in:individual,company,PARTNER_LEAD,AGENT,RESELLER',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:5120',
        ], [
            'customer_group_id.required' => 'Customer group is required.',
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists in this group.',
            'name.required' => 'Name is required.',
            'username.unique' => 'Username already exists.',
        ]);

        $user = auth('web')->user();
        $attachments = $this->handleAttachments($request);

        $data = [
            'customer_group_id' => $groupId,
            'code' => $request->code,
            'name' => $request->name,
            'tax_number' => $request->tax_number ?: null,
            'tax_name' => $request->tax_name ?: null,
            'tax_address' => $request->tax_address ?: null,
            'contact_person' => $request->contact_person ?: null,
            'email' => $request->email ?: null,
            'phone' => $request->phone ?: null,
            'mobile' => $request->mobile ?: null,
            'address' => $request->address ?: null,
            'city' => $request->city ?: null,
            'province' => $request->province ?: null,
            'postal_code' => $request->postal_code ?: null,
            'country' => $request->country ?: null,
            'lat' => $request->lat ?: null,
            'long' => $request->long ?: null,
            'identity_type' => $request->identity_type ?: null,
            'identity_number' => $request->identity_number ?: null,
            'attachments' => $attachments,
            'username' => $request->username ?: null,
            'has_app_access' => (bool) $request->has_app_access,
            'customer_type' => $request->customer_type ?: null,
            'notes' => $request->notes ?: null,
            'is_active' => $request->has('is_active'),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        Customer::create($data);

        return redirect()->route('customer.list.index')->with('success', 'Customer added successfully.');
    }

    public function editView(Request $request, $id)
    {
        $customer = Customer::where('id', $id)
            ->withTrashed()
            ->with([
                'customerGroup.branch',
                'agent',
                'reseller.agent',
                'latestPartnerApplication',
            ])
            ->firstOrFail();
        $groupIds = $this->getAccessibleGroupIds();

        if (!empty($groupIds) && !in_array($customer->customer_group_id, $groupIds)) {
            abort(403, 'Unauthorized.');
        }

        $customerGroups = !empty($groupIds)
            ? CustomerGroup::with('branch')
                ->whereIn('id', $groupIds)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get()
            : collect();

        return view('admin.customer.list.edit', compact('customer', 'customerGroups'));
    }

    public function editData(Request $request)
    {
        $customer = Customer::where('id', $request->id)->withTrashed()->firstOrFail();
        $groupIds = $this->getAccessibleGroupIds();

        if (!empty($groupIds) && !in_array($customer->customer_group_id, $groupIds)) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'id' => ['required', Rule::exists(Customer::class, 'id')],
            'customer_group_id' => ['required', Rule::exists(CustomerGroup::class, 'id')],
            'code' => ['required', 'string', 'max:50', Rule::unique(Customer::class, 'code')->where('customer_group_id', $request->customer_group_id)->ignore($request->id)->whereNull('deleted_at')],
            'name' => 'required|string|max:200',
            'tax_number' => 'nullable|string|max:50',
            'tax_name' => 'nullable|string|max:200',
            'tax_address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'lat' => 'nullable|numeric|between:-90,90',
            'long' => 'nullable|numeric|between:-180,180',
            'identity_type' => 'nullable|string|max:30',
            'identity_number' => 'nullable|string|max:50',
            'username' => ['nullable', 'string', 'max:100', Rule::unique(Customer::class, 'username')->ignore($request->id)->whereNull('deleted_at')],
            'password' => 'nullable|string|min:6',
            'has_app_access' => 'nullable|boolean',
            'customer_type' => 'nullable|in:individual,company,PARTNER_LEAD,AGENT,RESELLER',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:5120',
        ], [
            'code.required' => 'Code is required.',
            'name.required' => 'Name is required.',
        ]);

        $attachments = $this->handleAttachments($request, $customer->attachments ?? []);

        $data = [
            'customer_group_id' => $request->customer_group_id,
            'code' => $request->code,
            'name' => $request->name,
            'tax_number' => $request->tax_number ?: null,
            'tax_name' => $request->tax_name ?: null,
            'tax_address' => $request->tax_address ?: null,
            'contact_person' => $request->contact_person ?: null,
            'email' => $request->email ?: null,
            'phone' => $request->phone ?: null,
            'mobile' => $request->mobile ?: null,
            'address' => $request->address ?: null,
            'city' => $request->city ?: null,
            'province' => $request->province ?: null,
            'postal_code' => $request->postal_code ?: null,
            'country' => $request->country ?: null,
            'lat' => $request->lat ?: null,
            'long' => $request->long ?: null,
            'identity_type' => $request->identity_type ?: null,
            'identity_number' => $request->identity_number ?: null,
            'attachments' => $attachments,
            'username' => $request->username ?: null,
            'has_app_access' => (bool) $request->has_app_access,
            'customer_type' => $request->customer_type ?: null,
            'notes' => $request->notes ?: null,
            'is_active' => $request->has('is_active'),
            'updated_by' => auth('web')->id(),
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $customer->update($data);

        return redirect()->route('customer.list.index')->with('success', 'Customer updated successfully.');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'customer_id_deleted' => ['required', Rule::exists(Customer::class, 'id')],
        ]);

        $customer = Customer::findOrFail($request->customer_id_deleted);
        $groupIds = $this->getAccessibleGroupIds();

        if (!empty($groupIds) && !in_array($customer->customer_group_id, $groupIds)) {
            abort(403, 'Unauthorized.');
        }

        $customer->update(['deleted_by' => auth('web')->id()]);
        $customer->delete();

        return redirect()->route('customer.list.index')->with('success', 'Customer deleted successfully.');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'customer_id_restored' => ['required', Rule::exists(Customer::class, 'id')],
        ]);

        $customer = Customer::withTrashed()->findOrFail($request->customer_id_restored);
        $groupIds = $this->getAccessibleGroupIds();

        if (!empty($groupIds) && !in_array($customer->customer_group_id, $groupIds)) {
            abort(403, 'Unauthorized.');
        }

        $customer->update(['deleted_by' => null]);
        $customer->restore();

        return redirect()->route('customer.list.index')->with('success', 'Customer restored successfully.');
    }

    public function removeAttachment(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', Rule::exists(Customer::class, 'id')],
            'index' => 'required|integer|min:0',
        ]);

        $customer = Customer::findOrFail($request->customer_id);
        $groupIds = $this->getAccessibleGroupIds();

        if (!empty($groupIds) && !in_array($customer->customer_group_id, $groupIds)) {
            abort(403, 'Unauthorized.');
        }

        $attachments = $customer->attachments ?? [];
        $index = (int) $request->index;
        if (isset($attachments[$index])) {
            $path = $attachments[$index]['path'] ?? null;
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            array_splice($attachments, $index, 1);
            $customer->update(['attachments' => $attachments]);
        }

        return response()->json(['success' => true]);
    }
}
