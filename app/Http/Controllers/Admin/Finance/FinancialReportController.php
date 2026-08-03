<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Models\Accounting\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Services\Accounting\FinancialReportService;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $reportService
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

    public function balanceSheetView(Request $request)
    {
        $companies = $this->companiesQuery()->orderBy('name')->get();
        $companyId = $request->get('company_id') ?: $companies->first()?->id;
        $asOf = $request->get('as_of') ?: format_date_id(now());
        $asOfYmd = HelperController::parseDate($asOf);

        $report = null;
        if ($companyId) {
            $this->assertCompanyAccess($companyId);
            $report = $this->reportService->balanceSheet($companyId, $asOfYmd);
        }

        return view('admin.finance.reports.balance-sheet', compact(
            'companies',
            'companyId',
            'asOf',
            'report'
        ));
    }

    public function incomeStatementView(Request $request)
    {
        $companies = $this->companiesQuery()->orderBy('name')->get();
        $companyId = $request->get('company_id') ?: $companies->first()?->id;
        $dateFrom = $request->get('date_from') ?: format_date_id(now()->startOfMonth());
        $dateTo = $request->get('date_to') ?: format_date_id(now());
        $dateFromYmd = HelperController::parseDate($dateFrom);
        $dateToYmd = HelperController::parseDate($dateTo);

        $report = null;
        if ($companyId) {
            $this->assertCompanyAccess($companyId);
            $report = $this->reportService->incomeStatement($companyId, $dateFromYmd, $dateToYmd);
        }

        return view('admin.finance.reports.income-statement', compact(
            'companies',
            'companyId',
            'dateFrom',
            'dateTo',
            'report'
        ));
    }

    public function cashFlowView(Request $request)
    {
        $companies = $this->companiesQuery()->orderBy('name')->get();
        $companyId = $request->get('company_id') ?: $companies->first()?->id;
        $dateFrom = $request->get('date_from') ?: format_date_id(now()->startOfMonth());
        $dateTo = $request->get('date_to') ?: format_date_id(now());
        $dateFromYmd = HelperController::parseDate($dateFrom);
        $dateToYmd = HelperController::parseDate($dateTo);

        $report = null;
        if ($companyId) {
            $this->assertCompanyAccess($companyId);
            $report = $this->reportService->cashFlowIndirect($companyId, $dateFromYmd, $dateToYmd);
        }

        return view('admin.finance.reports.cash-flow', compact(
            'companies',
            'companyId',
            'dateFrom',
            'dateTo',
            'report'
        ));
    }

    public function generalLedgerView(Request $request)
    {
        $companies = $this->companiesQuery()->orderBy('name')->get();
        $companyId = $request->get('company_id') ?: $companies->first()?->id;
        $dateFrom = $request->get('date_from') ?: format_date_id(now()->startOfMonth());
        $dateTo = $request->get('date_to') ?: format_date_id(now());
        $dateFromYmd = HelperController::parseDate($dateFrom);
        $dateToYmd = HelperController::parseDate($dateTo);
        $accountId = $request->get('account_id') ?: null;
        $accountType = $request->get('account_type') ?: null;

        $accounts = collect();
        $report = null;
        if ($companyId) {
            $this->assertCompanyAccess($companyId);
            $accounts = ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where('is_header', false)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'account_type']);
            $report = $this->reportService->generalLedger(
                $companyId,
                $dateFromYmd,
                $dateToYmd,
                $accountId,
                $accountType
            );
        }

        $accountTypes = ChartOfAccount::ACCOUNT_TYPES;

        return view('admin.finance.reports.general-ledger', compact(
            'companies',
            'companyId',
            'dateFrom',
            'dateTo',
            'accountId',
            'accountType',
            'accounts',
            'accountTypes',
            'report'
        ));
    }
}
