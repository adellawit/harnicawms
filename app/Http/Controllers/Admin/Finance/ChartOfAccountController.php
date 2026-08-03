<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CashFlowCategory;
use App\Models\Accounting\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Services\Accounting\ChartOfAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChartOfAccountController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountService $coaService
    ) {}

    protected function companiesQuery()
    {
        $user = auth('web')->user();

        $query = BusinessUnit::query()
            ->whereNull('deleted_at')
            ->where('type_code', 'COMPANY')
            ->where('is_active', true);

        if (! $user->is_super_admin) {
            $userBu = $user->businessUnit;
            if (! $userBu) {
                return $query->whereRaw('1=0');
            }

            match ($userBu->type_code) {
                'HOLDING' => $query->where('parent_id', $userBu->id),
                'COMPANY' => $query->where('id', $userBu->id),
                'BRANCH' => $query->where('id', $userBu->parent_id),
                default => $query->whereRaw('1=0'),
            };
        }

        return $query;
    }

    protected function assertCompanyAccess(string $companyId): void
    {
        if (! $this->companiesQuery()->where('id', $companyId)->exists()) {
            abort(403, 'Company tidak dapat diakses.');
        }
    }

    public function indexView(Request $request)
    {
        $status = $request->get('status', 'active');
        $accountType = $request->get('account_type', '');
        $search = trim((string) $request->get('q', ''));

        $companies = $this->companiesQuery()->orderBy('name')->get();
        $companyId = $request->get('company_id') ?: $companies->first()?->id;
        $isFilter = $status !== 'active' || $accountType !== '' || $search !== '';

        $treeRows = [];
        if ($companyId) {
            $this->assertCompanyAccess($companyId);

            $query = ChartOfAccount::query()
                ->where('company_id', $companyId);

            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'deleted') {
                $query->onlyTrashed();
            } elseif ($status === 'all') {
                $query->withTrashed();
            }

            if ($accountType !== '') {
                $query->where('account_type', $accountType);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ILIKE', "%{$search}%")
                        ->orWhere('name', 'ILIKE', "%{$search}%");
                });
            }

            $accounts = $query->with('cashFlowCategory:id,code,name')->orderBy('code')->get();
            $treeRows = $this->coaService->buildTreeRows($accounts);
        }

        return view('admin.finance.chart-of-accounts.index', compact(
            'status',
            'accountType',
            'companyId',
            'isFilter',
            'companies',
            'treeRows',
            'search'
        ));
    }

    public function indexData(Request $request)
    {
        // Kept for route compatibility; list page uses server-rendered tree.
        return response()->json(['data' => []]);
    }

    public function insertView()
    {
        return view('admin.finance.chart-of-accounts.insert', [
            'companies' => $this->companiesQuery()->orderBy('name')->get(),
            'accountTypes' => ChartOfAccount::ACCOUNT_TYPES,
            'normalBalances' => ChartOfAccount::NORMAL_BALANCES,
            'cashFlowCategories' => CashFlowCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(),
            'parents' => collect(),
        ]);
    }

    public function insertData(Request $request)
    {
        $validated = $this->validatePayload($request);
        $this->assertCompanyAccess($validated['company_id']);

        $validated['level'] = $this->coaService->resolveLevel($validated['parent_id'] ?? null);
        $validated['is_header'] = (bool) ($validated['is_header'] ?? false);
        $validated['is_contra_account'] = (bool) ($validated['is_contra_account'] ?? false);
        $validated['is_cash_bank'] = (bool) ($validated['is_cash_bank'] ?? false);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        ChartOfAccount::create(array_merge($validated, [
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]));

        return redirect()
            ->route('finance.chart-of-accounts.index.view', ['company_id' => $validated['company_id']])
            ->with('success', 'Akun berhasil ditambahkan.');
    }

    public function editView(string $id)
    {
        $account = ChartOfAccount::withTrashed()->findOrFail($id);
        $this->assertCompanyAccess($account->company_id);

        $parents = ChartOfAccount::query()
            ->where('company_id', $account->company_id)
            ->where('id', '!=', $account->id)
            ->where('is_header', true)
            ->orderBy('code')
            ->get();

        return view('admin.finance.chart-of-accounts.edit', [
            'account' => $account,
            'companies' => $this->companiesQuery()->orderBy('name')->get(),
            'accountTypes' => ChartOfAccount::ACCOUNT_TYPES,
            'normalBalances' => ChartOfAccount::NORMAL_BALANCES,
            'cashFlowCategories' => CashFlowCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(),
            'parents' => $parents,
        ]);
    }

    public function editData(Request $request)
    {
        $account = ChartOfAccount::withTrashed()->findOrFail($request->input('id'));
        $this->assertCompanyAccess($account->company_id);

        $validated = $this->validatePayload($request, $account->id);
        $this->assertCompanyAccess($validated['company_id']);

        if (! empty($validated['parent_id']) && $validated['parent_id'] === $account->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Akun tidak boleh menjadi parent dirinya sendiri.',
            ]);
        }

        $validated['level'] = $this->coaService->resolveLevel($validated['parent_id'] ?? null);
        $validated['is_header'] = (bool) ($validated['is_header'] ?? false);
        $validated['is_contra_account'] = (bool) ($validated['is_contra_account'] ?? false);
        $validated['is_cash_bank'] = (bool) ($validated['is_cash_bank'] ?? false);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        $account->update(array_merge($validated, [
            'updated_by' => auth('web')->id(),
        ]));

        return redirect()
            ->route('finance.chart-of-accounts.index.view', ['company_id' => $validated['company_id']])
            ->with('success', 'Akun berhasil diperbarui.');
    }

    public function deleteData(Request $request)
    {
        $request->validate(['chart_of_account_id_deleted' => 'required|uuid']);

        $account = ChartOfAccount::findOrFail($request->input('chart_of_account_id_deleted'));
        $this->assertCompanyAccess($account->company_id);
        $this->coaService->assertCanDelete($account);

        $account->deleted_by = auth('web')->id();
        $account->save();
        $account->delete();

        return redirect()
            ->route('finance.chart-of-accounts.index.view', ['company_id' => $account->company_id])
            ->with('success', 'Akun berhasil dihapus.');
    }

    public function restoreData(Request $request)
    {
        $request->validate(['chart_of_account_id_restored' => 'required|uuid']);

        $account = ChartOfAccount::onlyTrashed()->findOrFail($request->input('chart_of_account_id_restored'));
        $this->assertCompanyAccess($account->company_id);

        $account->restore();
        $account->deleted_by = null;
        $account->updated_by = auth('web')->id();
        $account->save();

        return redirect()
            ->route('finance.chart-of-accounts.index.view', ['company_id' => $account->company_id])
            ->with('success', 'Akun berhasil direstore.');
    }

    public function seedTemplate(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|uuid',
        ]);

        $this->assertCompanyAccess($validated['company_id']);

        $result = $this->coaService->seedTemplate(
            $validated['company_id'],
            auth('web')->id(),
            true
        );

        return redirect()
            ->route('finance.chart-of-accounts.index.view', ['company_id' => $validated['company_id']])
            ->with('success', "Template COA: {$result['created']} dibuat, {$result['skipped']} dilewati, {$result['mappings']} mapping diisi.");
    }

    public function parentsData(Request $request)
    {
        $request->validate(['company_id' => 'required|uuid']);
        $this->assertCompanyAccess($request->get('company_id'));

        $parents = ChartOfAccount::query()
            ->where('company_id', $request->get('company_id'))
            ->where('is_header', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'level']);

        return response()->json([
            'data' => $parents->map(fn (ChartOfAccount $a) => [
                'id' => $a->id,
                'label' => $a->displayLabel(),
            ]),
        ]);
    }

    public function suggestCode(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|uuid',
            'parent_id' => 'nullable|uuid',
            'account_type' => ['nullable', Rule::in(array_keys(ChartOfAccount::ACCOUNT_TYPES))],
        ]);

        $this->assertCompanyAccess($validated['company_id']);

        $code = $this->coaService->suggestNextCode(
            $validated['company_id'],
            $validated['parent_id'] ?? null,
            $validated['account_type'] ?? null
        );

        return response()->json(['code' => $code]);
    }

    private function validatePayload(Request $request, ?string $ignoreId = null): array
    {
        $companyId = $request->input('company_id');
        $code = trim((string) $request->input('code', ''));

        if ($code === '' && $companyId) {
            $code = $this->coaService->suggestNextCode(
                $companyId,
                $request->input('parent_id') ?: null,
                $request->input('account_type')
            );
            $request->merge(['code' => $code]);
        }

        $validated = $request->validate([
            'company_id' => 'required|uuid',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('accounting.chart_of_accounts', 'code')
                    ->where(fn ($q) => $q->where('company_id', $request->input('company_id'))->whereNull('deleted_at'))
                    ->ignore($ignoreId),
            ],
            'name' => 'required|string|max:255',
            'account_type' => ['required', Rule::in(array_keys(ChartOfAccount::ACCOUNT_TYPES))],
            'normal_balance' => ['required', Rule::in(array_keys(ChartOfAccount::NORMAL_BALANCES))],
            'cash_flow_category_id' => [
                'nullable',
                'uuid',
                Rule::exists('accounting.cash_flow_categories', 'id')
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('accounting.chart_of_accounts', 'id')
                    ->where(fn ($q) => $q->where('company_id', $request->input('company_id'))->whereNull('deleted_at')),
            ],
            'is_header' => 'nullable',
            'is_contra_account' => 'nullable',
            'is_cash_bank' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $validated['is_header'] = $request->boolean('is_header');
        $validated['is_contra_account'] = $request->boolean('is_contra_account');
        $validated['is_cash_bank'] = $request->boolean('is_cash_bank');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['parent_id'] = $validated['parent_id'] ?? null;

        if (empty($validated['cash_flow_category_id'])) {
            $validated['cash_flow_category_id'] = CashFlowCategory::query()
                ->where('code', CashFlowCategory::CODE_NONE)
                ->value('id');
        }

        return $validated;
    }
}
