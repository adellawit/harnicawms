<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Models\Accounting\CashBankReconciliation;
use App\Models\BusinessUnit;
use App\Services\Accounting\CashBankService;
use Illuminate\Http\Request;

class CashBankController extends Controller
{
    public function __construct(
        private readonly CashBankService $cashBankService
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
            abort(403, 'Company is not accessible.');
        }
    }

    public function indexView(Request $request)
    {
        $companies = $this->companiesQuery()->orderBy('name')->get();
        $companyId = $request->get('company_id') ?: $companies->first()?->id;
        $asOf = $request->get('as_of') ?: format_date_id(now());
        $asOfYmd = HelperController::parseDate($asOf);

        $accounts = collect();
        if ($companyId) {
            $this->assertCompanyAccess($companyId);
            $accounts = $this->cashBankService->cashBankAccounts($companyId)->map(function ($account) use ($asOfYmd) {
                $account->book_balance = $this->cashBankService->bookBalance($account->id, $asOfYmd);

                return $account;
            });
        }

        return view('admin.finance.cash-bank.index', compact(
            'companies',
            'companyId',
            'accounts',
            'asOf'
        ));
    }

    public function mutationsView(Request $request, string $accountId)
    {
        $companies = $this->companiesQuery()->orderBy('name')->get();
        $companyId = $request->get('company_id') ?: $companies->first()?->id;
        if (! $companyId) {
            abort(404);
        }
        $this->assertCompanyAccess($companyId);

        $account = $this->cashBankService->assertCashBankAccount($companyId, $accountId);
        $dateFrom = $request->get('date_from') ?: format_date_id(now()->startOfMonth());
        $dateTo = $request->get('date_to') ?: format_date_id(now());
        $dateFromYmd = HelperController::parseDate($dateFrom);
        $dateToYmd = HelperController::parseDate($dateTo);

        $mutations = $this->cashBankService->mutations($account->id, $dateFromYmd, $dateToYmd);
        $balance = $this->cashBankService->bookBalance($account->id, $dateToYmd);

        return view('admin.finance.cash-bank.mutations', compact(
            'companies',
            'companyId',
            'account',
            'mutations',
            'dateFrom',
            'dateTo',
            'balance'
        ));
    }

    public function reconciliationStart(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|uuid',
            'account_id' => 'required|uuid',
            'reconciliation_date' => 'required|string',
        ]);

        $this->assertCompanyAccess($validated['company_id']);

        $recon = $this->cashBankService->startOrResumeReconciliation(
            $validated['company_id'],
            $validated['account_id'],
            $validated['reconciliation_date'],
            auth('web')->id()
        );

        return redirect()->route('finance.cash-bank.reconciliation.view', $recon->id);
    }

    public function reconciliationView(string $id)
    {
        $recon = CashBankReconciliation::with([
            'account',
            'company:id,name,code',
            'lines.journalLine.journal',
        ])->findOrFail($id);

        $this->assertCompanyAccess($recon->company_id);

        if ($recon->isDraft()) {
            $this->cashBankService->syncReconciliationCandidates($recon);
            $recon->load(['lines.journalLine.journal']);
        }

        $lines = $recon->lines
            ->sortBy(fn ($l) => [
                optional($l->journalLine?->journal)->journal_date?->format('Y-m-d') ?? '',
                optional($l->journalLine?->journal)->journal_no ?? '',
            ])
            ->values();

        $canEdit = $recon->isDraft() && session('permissions.Cash & Bank.is_update', false) == 1;

        return view('admin.finance.cash-bank.reconciliation', compact('recon', 'lines', 'canEdit'));
    }

    public function reconciliationSave(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|uuid',
            'statement_balance' => 'nullable',
            'notes' => 'nullable|string|max:1000',
            'cleared' => 'nullable|array',
            'cleared.*' => 'uuid',
            'action' => 'nullable|in:save,complete',
        ]);

        $recon = CashBankReconciliation::findOrFail($validated['id']);
        $this->assertCompanyAccess($recon->company_id);

        $recon = $this->cashBankService->saveDraft(
            $recon,
            $validated['statement_balance'] ?? 0,
            $validated['cleared'] ?? [],
            $validated['notes'] ?? null,
            auth('web')->id()
        );

        if (($validated['action'] ?? 'save') === 'complete') {
            $this->cashBankService->complete($recon, auth('web')->id());

            return redirect()
                ->route('finance.cash-bank.reconciliation.view', $recon->id)
                ->with('success', 'Reconciliation completed.');
        }

        return redirect()
            ->route('finance.cash-bank.reconciliation.view', $recon->id)
            ->with('success', 'Reconciliation draft saved.');
    }

    public function reconciliationReopen(Request $request)
    {
        $validated = $request->validate(['id' => 'required|uuid']);
        $recon = CashBankReconciliation::findOrFail($validated['id']);
        $this->assertCompanyAccess($recon->company_id);

        $this->cashBankService->reopen($recon, auth('web')->id());

        return redirect()
            ->route('finance.cash-bank.reconciliation.view', $recon->id)
            ->with('success', 'Reconciliation reopened.');
    }

    public function reconciliationHistory(Request $request, string $accountId)
    {
        $companyId = $request->get('company_id');
        if (! $companyId) {
            abort(404);
        }
        $this->assertCompanyAccess($companyId);
        $account = $this->cashBankService->assertCashBankAccount($companyId, $accountId);

        $items = CashBankReconciliation::query()
            ->where('account_id', $account->id)
            ->whereNull('deleted_at')
            ->orderByDesc('reconciliation_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.finance.cash-bank.reconciliation-history', compact(
            'account',
            'companyId',
            'items'
        ));
    }
}
