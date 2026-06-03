<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\MethodPayment;
use App\Services\XenditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class MethodPaymentController extends Controller
{
    public function __construct(
        protected XenditService $xendit,
    ) {}

    protected function getAccessibleBranchIds(): array
    {
        $user = auth('web')->user();
        if (! $user || ! $user->current_business_unit_id) {
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

    /**
     * @return array<string, mixed>
     */
    protected function pgFormOptions(): array
    {
        return [
            'pgGroupCodes' => $this->xendit->paymentGroupCodes(),
            'channelCatalogByGroup' => $this->xendit->channelCatalogByGroup(),
            'xenditConfigured' => $this->xendit->isConfigured(),
        ];
    }

    /**
     * @return array{uses_payment_gateway: bool, gateway_provider: ?string, payment_group_code: ?string, gateway_channel_code: ?string}
     */
    protected function resolvePgAttributes(Request $request): array
    {
        $settlementType = $request->input('settlement_type', 'manual');

        if ($settlementType === 'manual') {
            return [
                'uses_payment_gateway' => false,
                'gateway_provider' => null,
                'payment_group_code' => null,
                'gateway_channel_code' => null,
            ];
        }

        $gatewayProvider = 'xendit';

        if ($settlementType === 'pg_channel') {
            return [
                'uses_payment_gateway' => true,
                'gateway_provider' => $gatewayProvider,
                'payment_group_code' => strtoupper((string) $request->payment_group_code),
                'gateway_channel_code' => strtoupper((string) $request->gateway_channel_code),
            ];
        }

        return [
            'uses_payment_gateway' => true,
            'gateway_provider' => $gatewayProvider,
            'payment_group_code' => strtoupper((string) $request->code),
            'gateway_channel_code' => null,
        ];
    }

    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        return view('admin.pos.method-payment.index', array_merge(
            compact('status', 'isFilter'),
            $this->pgFormOptions(),
            ['canSyncPg' => $this->xendit->isConfigured()]
        ));
    }

    public function indexData(Request $request)
    {
        $branchIds = $this->getAccessibleBranchIds();

        $data = MethodPayment::query()
            ->select(
                'master_data.method_payments.id',
                'master_data.method_payments.branch_id',
                'master_data.method_payments.code',
                'master_data.method_payments.name',
                'master_data.method_payments.sort_order',
                'master_data.method_payments.is_active',
                'master_data.method_payments.uses_payment_gateway',
                'master_data.method_payments.gateway_provider',
                'master_data.method_payments.payment_group_code',
                'master_data.method_payments.gateway_channel_code',
                'master_data.method_payments.created_at',
                'master_data.method_payments.deleted_at',
                'bu.name as branch_name'
            )
            ->leftJoin('master_data.business_units as bu', 'master_data.method_payments.branch_id', '=', 'bu.id');

        if (! empty($branchIds)) {
            $data = $data->whereIn('master_data.method_payments.branch_id', $branchIds);
        }

        if ($request->status === 'active') {
            // default
        } elseif ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('master_data.method_payments.sort_order')
            ->orderBy('master_data.method_payments.name');

        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('master_data.method_payments.name', 'ilike', "%{$search}%")
                            ->orWhere('master_data.method_payments.code', 'ilike', "%{$search}%")
                            ->orWhere('master_data.method_payments.gateway_channel_code', 'ilike', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        $branchIds = $this->getAccessibleBranchIds();
        $branches = ! empty($branchIds)
            ? BusinessUnit::whereIn('id', $branchIds)->orderBy('name')->get(['id', 'code', 'name'])
            : collect();
        $defaultBranchId = $branches->count() === 1
            ? $branches->first()?->id
            : $this->getBranchId();

        return view('admin.pos.method-payment.insert', array_merge(
            compact('branches', 'branchId'),
            ['branchId' => $defaultBranchId],
            $this->pgFormOptions()
        ));
    }

    public function insertData(Request $request)
    {
        $branchIds = $this->getAccessibleBranchIds();
        $branchId = $request->branch_id ?: $this->getBranchId();

        if (! empty($branchIds) && $branchId && ! in_array($branchId, $branchIds)) {
            abort(403, 'Unauthorized branch.');
        }

        $request->validate([
            'branch_id' => ['required', Rule::exists('master_data.business_units', 'id')],
            'settlement_type' => 'required|in:manual,pg_group,pg_channel',
            'code' => ['required', 'string', 'max:50', Rule::unique('master_data.method_payments', 'code')->where('branch_id', $branchId)->whereNull('deleted_at')],
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'payment_group_code' => 'nullable|string|max:50',
            'gateway_channel_code' => 'nullable|string|max:50',
        ], [
            'branch_id.required' => 'Branch is required.',
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists in this branch.',
            'name.required' => 'Name is required.',
        ]);

        if ($request->settlement_type === 'pg_channel') {
            $request->validate([
                'payment_group_code' => 'required|string|max:50',
                'gateway_channel_code' => 'required|string|max:50',
            ]);
        }

        if (in_array($request->settlement_type, ['pg_group', 'pg_channel'], true) && ! $this->xendit->isConfigured()) {
            return back()->withInput()->withErrors([
                'settlement_type' => 'Payment Gateway (Xendit) belum dikonfigurasi. Set XENDIT_ENABLED dan XENDIT_SECRET_KEY di .env.',
            ]);
        }

        $pg = $this->resolvePgAttributes($request);
        $user = auth('web')->user();

        MethodPayment::create(array_merge([
            'branch_id' => $branchId,
            'code' => strtoupper((string) $request->code),
            'name' => $request->name,
            'description' => $request->description ?: null,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active'),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $pg));

        return redirect()->route('pos.method-payment.index')->with('success', 'Method payment added successfully.');
    }

    public function editView(Request $request, $id)
    {
        $methodPayment = MethodPayment::where('id', $id)->withTrashed()->with('branch')->firstOrFail();
        $branchIds = $this->getAccessibleBranchIds();

        if (! empty($branchIds) && ! in_array($methodPayment->branch_id, $branchIds)) {
            abort(403, 'Unauthorized.');
        }

        return view('admin.pos.method-payment.edit', array_merge(
            compact('methodPayment'),
            $this->pgFormOptions()
        ));
    }

    public function editData(Request $request)
    {
        $methodPayment = MethodPayment::where('id', $request->id)->withTrashed()->firstOrFail();
        $branchIds = $this->getAccessibleBranchIds();

        if (! empty($branchIds) && ! in_array($methodPayment->branch_id, $branchIds)) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'id' => 'required|exists:master_data.method_payments,id',
            'settlement_type' => 'required|in:manual,pg_group,pg_channel',
            'code' => ['required', 'string', 'max:50', Rule::unique('master_data.method_payments', 'code')->where('branch_id', $methodPayment->branch_id)->ignore($request->id)->whereNull('deleted_at')],
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'payment_group_code' => 'nullable|string|max:50',
            'gateway_channel_code' => 'nullable|string|max:50',
        ], [
            'code.required' => 'Code is required.',
            'name.required' => 'Name is required.',
        ]);

        if ($request->settlement_type === 'pg_channel') {
            $request->validate([
                'payment_group_code' => 'required|string|max:50',
                'gateway_channel_code' => 'required|string|max:50',
            ]);
        }

        if (in_array($request->settlement_type, ['pg_group', 'pg_channel'], true) && ! $this->xendit->isConfigured()) {
            return back()->withInput()->withErrors([
                'settlement_type' => 'Payment Gateway (Xendit) belum dikonfigurasi.',
            ]);
        }

        $pg = $this->resolvePgAttributes($request);

        $methodPayment->update(array_merge([
            'code' => strtoupper((string) $request->code),
            'name' => $request->name,
            'description' => $request->description ?: null,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active'),
            'updated_by' => auth('web')->id(),
        ], $pg));

        return redirect()->route('pos.method-payment.index')->with('success', 'Method payment updated successfully.');
    }

    public function syncPgChannels(Request $request)
    {
        if (! $this->xendit->isConfigured()) {
            return redirect()->route('pos.method-payment.index')
                ->with('error', 'Xendit belum dikonfigurasi. Tidak dapat sinkron channel PG.');
        }

        $branchIds = $this->getAccessibleBranchIds();
        $branchId = $request->get('branch_id') ?: $this->getBranchId();

        if (! $branchId || (! empty($branchIds) && ! in_array($branchId, $branchIds))) {
            abort(403, 'Unauthorized branch.');
        }

        $user = auth('web')->user();
        $activeCodes = $this->xendit->getMerchantActiveChannelCodes();
        $synced = 0;

        foreach ($activeCodes as $code) {
            $meta = $this->xendit->resolveChannelMeta($code);
            $groupCode = strtoupper((string) $meta['group']);
            $channelCode = strtoupper((string) $code);
            $pgCode = 'PG_'.$channelCode;

            MethodPayment::withTrashed()->updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'gateway_channel_code' => $channelCode,
                ],
                [
                    'code' => $pgCode,
                    'name' => $meta['label'],
                    'description' => 'Channel PG Xendit — '.$groupCode,
                    'sort_order' => 100,
                    'is_active' => true,
                    'uses_payment_gateway' => true,
                    'gateway_provider' => 'xendit',
                    'payment_group_code' => $groupCode,
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'updated_by' => $user?->id,
                    'created_by' => $user?->id,
                ]
            );
            $synced++;
        }

        return redirect()->route('pos.method-payment.index')
            ->with('success', "Sinkron PG: {$synced} channel diperbarui untuk cabang ini.");
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'method_payment_id_deleted' => 'required|exists:master_data.method_payments,id',
        ]);

        $methodPayment = MethodPayment::findOrFail($request->method_payment_id_deleted);
        $branchIds = $this->getAccessibleBranchIds();

        if (! empty($branchIds) && ! in_array($methodPayment->branch_id, $branchIds)) {
            abort(403, 'Unauthorized.');
        }

        $methodPayment->update(['deleted_by' => auth('web')->id()]);
        $methodPayment->delete();

        return redirect()->route('pos.method-payment.index')->with('success', 'Method payment deleted successfully.');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'method_payment_id_restored' => 'required|exists:master_data.method_payments,id',
        ]);

        $methodPayment = MethodPayment::withTrashed()->findOrFail($request->method_payment_id_restored);
        $branchIds = $this->getAccessibleBranchIds();

        if (! empty($branchIds) && ! in_array($methodPayment->branch_id, $branchIds)) {
            abort(403, 'Unauthorized.');
        }

        $methodPayment->update(['deleted_by' => null]);
        $methodPayment->restore();

        return redirect()->route('pos.method-payment.index')->with('success', 'Method payment restored successfully.');
    }
}
