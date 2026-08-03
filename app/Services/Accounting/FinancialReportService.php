<?php

namespace App\Services\Accounting;

use App\Models\Accounting\CashFlowCategory;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\FiscalCalendar;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinancialReportService
{
    /**
     * Batch raw ledger balances (debit - credit) as-of date inclusive.
     *
     * @return array<string, float> account_id => raw
     */
    public function rawBalancesAsOf(string $companyId, string $asOfYmd, ?array $accountIds = null): array
    {
        $query = JournalLine::query()
            ->join('accounting.journals as j', 'j.id', '=', 'accounting.journal_lines.journal_id')
            ->where('j.company_id', $companyId)
            ->where('j.status', Journal::STATUS_POSTED)
            ->whereNull('j.deleted_at')
            ->whereDate('j.journal_date', '<=', $asOfYmd)
            ->selectRaw('accounting.journal_lines.account_id, COALESCE(SUM(accounting.journal_lines.debit - accounting.journal_lines.credit), 0) as balance')
            ->groupBy('accounting.journal_lines.account_id');

        if ($accountIds !== null) {
            if ($accountIds === []) {
                return [];
            }
            $query->whereIn('accounting.journal_lines.account_id', $accountIds);
        }

        return $query->pluck('balance', 'account_id')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /**
     * Batch raw ledger movement in period (inclusive).
     *
     * @return array<string, float> account_id => raw
     */
    public function rawBalancesPeriod(string $companyId, string $dateFromYmd, string $dateToYmd, ?array $accountIds = null): array
    {
        $query = JournalLine::query()
            ->join('accounting.journals as j', 'j.id', '=', 'accounting.journal_lines.journal_id')
            ->where('j.company_id', $companyId)
            ->where('j.status', Journal::STATUS_POSTED)
            ->whereNull('j.deleted_at')
            ->whereDate('j.journal_date', '>=', $dateFromYmd)
            ->whereDate('j.journal_date', '<=', $dateToYmd)
            ->selectRaw('accounting.journal_lines.account_id, COALESCE(SUM(accounting.journal_lines.debit - accounting.journal_lines.credit), 0) as balance')
            ->groupBy('accounting.journal_lines.account_id');

        if ($accountIds !== null) {
            if ($accountIds === []) {
                return [];
            }
            $query->whereIn('accounting.journal_lines.account_id', $accountIds);
        }

        return $query->pluck('balance', 'account_id')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    public function presentAmount(ChartOfAccount $account, float $raw): float
    {
        $amount = $account->normal_balance === 'credit' ? -$raw : $raw;

        return round($amount, 2);
    }

    /**
     * Active accounts for company filtered by account types.
     *
     * @param  list<string>  $types
     */
    public function activeAccounts(string $companyId, array $types): Collection
    {
        return ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('account_type', $types)
            ->orderBy('code')
            ->get();
    }

    /**
     * Build indented tree rows with presentation amounts.
     * Detail = map or 0; header = sum of children.
     *
     * @param  Collection<int, ChartOfAccount>  $accounts
     * @param  array<string, float>  $rawByAccount
     * @return list<array{account: ChartOfAccount, depth: int, amount: float, is_header: bool}>
     */
    public function buildAmountTree(Collection $accounts, array $rawByAccount): array
    {
        if ($accounts->isEmpty()) {
            return [];
        }

        $byParent = $accounts->groupBy(fn (ChartOfAccount $a) => $a->parent_id ?? 'root');
        $amountCache = [];

        $compute = function (ChartOfAccount $account) use (&$compute, &$amountCache, $byParent, $rawByAccount): float {
            if (array_key_exists($account->id, $amountCache)) {
                return $amountCache[$account->id];
            }

            $children = ($byParent->get($account->id) ?? collect())->values();
            if ($account->is_header || $children->isNotEmpty()) {
                $sum = 0.0;
                foreach ($children as $child) {
                    $sum += $compute($child);
                }
                $amountCache[$account->id] = round($sum, 2);

                return $amountCache[$account->id];
            }

            $raw = (float) ($rawByAccount[$account->id] ?? 0);
            $amountCache[$account->id] = $this->presentAmount($account, $raw);

            return $amountCache[$account->id];
        };

        foreach ($accounts as $account) {
            $compute($account);
        }

        $rows = [];
        $walk = function (?string $parentId, int $depth) use (&$walk, &$rows, $byParent, $amountCache) {
            $key = $parentId ?? 'root';
            $children = ($byParent->get($key) ?? collect())
                ->sortBy('code', SORT_NATURAL)
                ->values();

            foreach ($children as $account) {
                $rows[] = [
                    'account' => $account,
                    'depth' => $depth,
                    'amount' => $amountCache[$account->id] ?? 0.0,
                    'is_header' => (bool) $account->is_header,
                ];
                $walk($account->id, $depth + 1);
            }
        };

        $walk(null, 0);

        $seen = collect($rows)->map(fn ($r) => $r['account']->id)->all();
        foreach ($accounts->sortBy('code', SORT_NATURAL) as $account) {
            if (! in_array($account->id, $seen, true)) {
                $rows[] = [
                    'account' => $account,
                    'depth' => 0,
                    'amount' => $amountCache[$account->id] ?? 0.0,
                    'is_header' => (bool) $account->is_header,
                ];
            }
        }

        return $rows;
    }

    /**
     * Sum of root-level presentation amounts in a typed tree.
     *
     * @param  list<array{depth: int, amount: float}>  $rows
     */
    public function sectionTotal(array $rows): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            if ($row['depth'] === 0) {
                $total += $row['amount'];
            }
        }

        return round($total, 2);
    }

    public function resolveFiscalYearStart(string $companyId, string $asOfYmd): string
    {
        $calendar = FiscalCalendar::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $asOfYmd)
            ->whereDate('end_date', '>=', $asOfYmd)
            ->orderBy('is_closed')
            ->orderByDesc('start_date')
            ->first();

        if ($calendar) {
            return Carbon::parse($calendar->start_date)->toDateString();
        }

        return Carbon::parse($asOfYmd)->startOfYear()->toDateString();
    }

    /**
     * Net income for period using presentation amounts (revenue − expense).
     */
    public function netIncome(string $companyId, string $dateFromYmd, string $dateToYmd): float
    {
        $accounts = $this->activeAccounts($companyId, ['revenue', 'expense']);
        $raw = $this->rawBalancesPeriod($companyId, $dateFromYmd, $dateToYmd, $accounts->pluck('id')->all());

        $revenue = 0.0;
        $expense = 0.0;
        foreach ($accounts->where('is_header', false) as $account) {
            $amount = $this->presentAmount($account, (float) ($raw[$account->id] ?? 0));
            if ($account->account_type === 'revenue') {
                $revenue += $amount;
            } else {
                $expense += $amount;
            }
        }

        return round($revenue - $expense, 2);
    }

    /**
     * @return array{
     *   assets: list,
     *   liabilities: list,
     *   equity: list,
     *   total_assets: float,
     *   total_liabilities: float,
     *   total_equity_book: float,
     *   current_year_earnings: float,
     *   total_liabilities_and_equity: float,
     *   is_balanced: bool,
     *   difference: float,
     *   fy_start: string,
     *   as_of: string
     * }
     */
    public function balanceSheet(string $companyId, string $asOfYmd): array
    {
        $accounts = $this->activeAccounts($companyId, ['asset', 'liability', 'equity']);
        $raw = $this->rawBalancesAsOf($companyId, $asOfYmd, $accounts->pluck('id')->all());

        $assets = $this->buildAmountTree($accounts->where('account_type', 'asset')->values(), $raw);
        $liabilities = $this->buildAmountTree($accounts->where('account_type', 'liability')->values(), $raw);
        $equity = $this->buildAmountTree($accounts->where('account_type', 'equity')->values(), $raw);

        $totalAssets = $this->sectionTotal($assets);
        $totalLiabilities = $this->sectionTotal($liabilities);
        $totalEquityBook = $this->sectionTotal($equity);

        $fyStart = $this->resolveFiscalYearStart($companyId, $asOfYmd);
        $cye = $this->netIncome($companyId, $fyStart, $asOfYmd);

        $totalLE = round($totalLiabilities + $totalEquityBook + $cye, 2);
        $difference = round($totalAssets - $totalLE, 2);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity_book' => $totalEquityBook,
            'current_year_earnings' => $cye,
            'total_liabilities_and_equity' => $totalLE,
            'is_balanced' => abs($difference) < 0.01,
            'difference' => $difference,
            'fy_start' => $fyStart,
            'as_of' => $asOfYmd,
        ];
    }

    /**
     * @return array{
     *   revenue: list,
     *   expense: list,
     *   total_revenue: float,
     *   total_expense: float,
     *   net_income: float,
     *   date_from: string,
     *   date_to: string
     * }
     */
    public function incomeStatement(string $companyId, string $dateFromYmd, string $dateToYmd): array
    {
        $accounts = $this->activeAccounts($companyId, ['revenue', 'expense']);
        $raw = $this->rawBalancesPeriod($companyId, $dateFromYmd, $dateToYmd, $accounts->pluck('id')->all());

        $revenue = $this->buildAmountTree($accounts->where('account_type', 'revenue')->values(), $raw);
        $expense = $this->buildAmountTree($accounts->where('account_type', 'expense')->values(), $raw);

        $totalRevenue = $this->sectionTotal($revenue);
        $totalExpense = $this->sectionTotal($expense);

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income' => round($totalRevenue - $totalExpense, 2),
            'date_from' => $dateFromYmd,
            'date_to' => $dateToYmd,
        ];
    }

    /**
     * Cash effect of presentation balance change for BS account.
     * Asset increase (presentation ↑) uses cash → negative CF.
     * Liability/Equity increase provides cash → positive CF.
     */
    public function cashEffectFromChange(ChartOfAccount $account, float $presentationChange): float
    {
        if (in_array($account->account_type, ['asset'], true)) {
            return round(-$presentationChange, 2);
        }

        // liability, equity
        return round($presentationChange, 2);
    }

    /**
     * Sum presentation book balance of cash/bank detail accounts as-of.
     */
    public function cashTotalAsOf(string $companyId, string $asOfYmd): float
    {
        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_header', false)
            ->where('is_cash_bank', true)
            ->get();

        if ($accounts->isEmpty()) {
            return 0.0;
        }

        $raw = $this->rawBalancesAsOf($companyId, $asOfYmd, $accounts->pluck('id')->all());
        $total = 0.0;
        foreach ($accounts as $account) {
            $total += $this->presentAmount($account, (float) ($raw[$account->id] ?? 0));
        }

        return round($total, 2);
    }

    /**
     * @return array{
     *   net_income: float,
     *   operating: list,
     *   investing: list,
     *   financing: list,
     *   operating_total: float,
     *   investing_total: float,
     *   financing_total: float,
     *   net_change: float,
     *   cash_beginning: float,
     *   cash_ending: float,
     *   cash_ending_computed: float,
     *   is_reconciled: bool,
     *   difference: float,
     *   date_from: string,
     *   date_to: string
     * }
     */
    public function cashFlowIndirect(string $companyId, string $dateFromYmd, string $dateToYmd): array
    {
        $ni = $this->netIncome($companyId, $dateFromYmd, $dateToYmd);

        $categories = CashFlowCategory::query()
            ->whereIn('code', [
                CashFlowCategory::CODE_OPERATING,
                CashFlowCategory::CODE_INVESTING,
                CashFlowCategory::CODE_FINANCING,
            ])
            ->get()
            ->keyBy('code');

        $categoryIds = $categories->pluck('id', 'code');

        $accounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_header', false)
            ->where('is_cash_bank', false)
            ->whereIn('account_type', ['asset', 'liability', 'equity'])
            ->whereIn('cash_flow_category_id', $categoryIds->values()->all())
            ->orderBy('code')
            ->get();

        $beginDate = Carbon::parse($dateFromYmd)->subDay()->toDateString();
        $rawBegin = $this->rawBalancesAsOf($companyId, $beginDate, $accounts->pluck('id')->all());
        $rawEnd = $this->rawBalancesAsOf($companyId, $dateToYmd, $accounts->pluck('id')->all());

        $sections = [
            CashFlowCategory::CODE_OPERATING => [],
            CashFlowCategory::CODE_INVESTING => [],
            CashFlowCategory::CODE_FINANCING => [],
        ];

        foreach ($accounts as $account) {
            $code = $categories->firstWhere('id', $account->cash_flow_category_id)?->code;
            if (! $code || ! isset($sections[$code])) {
                continue;
            }

            $presBegin = $this->presentAmount($account, (float) ($rawBegin[$account->id] ?? 0));
            $presEnd = $this->presentAmount($account, (float) ($rawEnd[$account->id] ?? 0));
            $change = round($presEnd - $presBegin, 2);
            $cashEffect = $this->cashEffectFromChange($account, $change);

            $sections[$code][] = [
                'account' => $account,
                'depth' => 0,
                'balance_change' => $change,
                'amount' => $cashEffect,
                'is_header' => false,
            ];
        }

        // Ensure all tagged accounts appear (already included); empty sections stay empty.
        // Also include zero-balance tagged accounts — already in $accounts with change 0.

        $sumSection = function (array $rows): float {
            return round(array_sum(array_column($rows, 'amount')), 2);
        };

        $operatingAdj = $sumSection($sections[CashFlowCategory::CODE_OPERATING]);
        $investingTotal = $sumSection($sections[CashFlowCategory::CODE_INVESTING]);
        $financingTotal = $sumSection($sections[CashFlowCategory::CODE_FINANCING]);
        $operatingTotal = round($ni + $operatingAdj, 2);
        $netChange = round($operatingTotal + $investingTotal + $financingTotal, 2);

        $cashBeginning = $this->cashTotalAsOf($companyId, $beginDate);
        $cashEnding = $this->cashTotalAsOf($companyId, $dateToYmd);
        $cashEndingComputed = round($cashBeginning + $netChange, 2);
        $difference = round($cashEnding - $cashEndingComputed, 2);

        return [
            'net_income' => $ni,
            'operating' => $sections[CashFlowCategory::CODE_OPERATING],
            'investing' => $sections[CashFlowCategory::CODE_INVESTING],
            'financing' => $sections[CashFlowCategory::CODE_FINANCING],
            'operating_adjustments_total' => $operatingAdj,
            'operating_total' => $operatingTotal,
            'investing_total' => $investingTotal,
            'financing_total' => $financingTotal,
            'net_change' => $netChange,
            'cash_beginning' => $cashBeginning,
            'cash_ending' => $cashEnding,
            'cash_ending_computed' => $cashEndingComputed,
            'is_reconciled' => abs($difference) < 0.01,
            'difference' => $difference,
            'date_from' => $dateFromYmd,
            'date_to' => $dateToYmd,
            'cash_begin_as_of' => $beginDate,
        ];
    }

    /**
     * General Ledger: detail accounts grouped with opening, lines, running, closing.
     * Accounts without mutations still appear (opening/closing may be 0).
     *
     * @return array{
     *   sections: list<array{
     *     account: ChartOfAccount,
     *     opening_balance: float,
     *     closing_balance: float,
     *     total_debit: float,
     *     total_credit: float,
     *     lines: list<array{
     *       journal_id: string,
     *       journal_no: ?string,
     *       journal_date: string,
     *       description: ?string,
     *       debit: float,
     *       credit: float,
     *       running_balance: float
     *     }>
     *   }>,
     *   date_from: string,
     *   date_to: string,
     *   account_count: int
     * }
     */
    public function generalLedger(
        string $companyId,
        string $dateFromYmd,
        string $dateToYmd,
        ?string $accountId = null,
        ?string $accountType = null
    ): array {
        $types = array_keys(ChartOfAccount::ACCOUNT_TYPES);
        if ($accountType && in_array($accountType, $types, true)) {
            $types = [$accountType];
        }

        $accountsQuery = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_header', false)
            ->whereIn('account_type', $types)
            ->orderBy('code');

        if ($accountId) {
            $accountsQuery->where('id', $accountId);
        }

        $accounts = $accountsQuery->get();
        $accountIds = $accounts->pluck('id')->all();

        $beginDate = Carbon::parse($dateFromYmd)->subDay()->toDateString();
        $rawOpen = $accountIds === []
            ? []
            : $this->rawBalancesAsOf($companyId, $beginDate, $accountIds);

        $linesByAccount = [];
        if ($accountIds !== []) {
            $periodLines = JournalLine::query()
                ->with('journal')
                ->whereIn('account_id', $accountIds)
                ->whereHas('journal', function ($q) use ($companyId, $dateFromYmd, $dateToYmd) {
                    $q->where('company_id', $companyId)
                        ->where('status', Journal::STATUS_POSTED)
                        ->whereNull('deleted_at')
                        ->whereDate('journal_date', '>=', $dateFromYmd)
                        ->whereDate('journal_date', '<=', $dateToYmd);
                })
                ->get()
                ->sortBy(fn (JournalLine $line) => [
                    optional($line->journal)->journal_date?->format('Y-m-d') ?? '',
                    optional($line->journal)->journal_no ?? '',
                    $line->line_no,
                ])
                ->values();

            foreach ($periodLines as $line) {
                $linesByAccount[$line->account_id][] = $line;
            }
        }

        $sections = [];
        foreach ($accounts as $account) {
            $openingRaw = (float) ($rawOpen[$account->id] ?? 0);
            $opening = $this->presentAmount($account, $openingRaw);
            $runningRaw = $openingRaw;
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            $lines = [];

            foreach ($linesByAccount[$account->id] ?? [] as $line) {
                $debit = round((float) $line->debit, 2);
                $credit = round((float) $line->credit, 2);
                $totalDebit = round($totalDebit + $debit, 2);
                $totalCredit = round($totalCredit + $credit, 2);
                $runningRaw = round($runningRaw + $debit - $credit, 2);

                $journalNo = $line->journal?->journal_no;
                $journalId = $line->journal_id;

                $lines[] = [
                    'journal_id' => $journalId,
                    'journal_no' => $journalNo ?: null,
                    'journal_label' => $journalNo ?: ($journalId ? substr((string) $journalId, 0, 8) : '—'),
                    'journal_date' => format_date_id($line->journal?->journal_date),
                    'description' => $line->description ?: $line->journal?->description,
                    'debit' => $debit,
                    'credit' => $credit,
                    'running_balance' => $this->presentAmount($account, $runningRaw),
                ];
            }

            $sections[] = [
                'account' => $account,
                'opening_balance' => $opening,
                'closing_balance' => $this->presentAmount($account, $runningRaw),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'lines' => $lines,
            ];
        }

        return [
            'sections' => $sections,
            'date_from' => $dateFromYmd,
            'date_to' => $dateToYmd,
            'account_count' => count($sections),
        ];
    }
}
