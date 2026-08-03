<?php

namespace App\Services\Accounting;

use App\Http\Controllers\Admin\HelperController;
use App\Models\Accounting\CashBankReconciliation;
use App\Models\Accounting\CashBankReconciliationLine;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashBankService
{
    public function cashBankAccounts(string $companyId): Collection
    {
        return ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_header', false)
            ->where('is_cash_bank', true)
            ->orderBy('code')
            ->get();
    }

    public function assertCashBankAccount(string $companyId, string $accountId): ChartOfAccount
    {
        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('id', $accountId)
            ->where('is_cash_bank', true)
            ->where('is_header', false)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'account' => 'Account is not a Cash & Bank account.',
            ]);
        }

        return $account;
    }

    /**
     * Book balance = sum(debit - credit) of posted journal lines up to as-of date.
     * Asset cash/bank uses debit-normal; contra handled via line signs.
     */
    public function bookBalance(string $accountId, string $asOfYmd): float
    {
        $row = JournalLine::query()
            ->join('accounting.journals as j', 'j.id', '=', 'accounting.journal_lines.journal_id')
            ->where('accounting.journal_lines.account_id', $accountId)
            ->where('j.status', Journal::STATUS_POSTED)
            ->whereNull('j.deleted_at')
            ->whereDate('j.journal_date', '<=', $asOfYmd)
            ->selectRaw('COALESCE(SUM(accounting.journal_lines.debit - accounting.journal_lines.credit), 0) as balance')
            ->value('balance');

        return round((float) $row, 2);
    }

    /**
     * @return list<array{
     *   journal_line_id: string,
     *   journal_id: string,
     *   journal_no: string,
     *   journal_date: string,
     *   description: ?string,
     *   debit: float,
     *   credit: float,
     *   running_balance: float,
     *   is_reconciled: bool
     * }>
     */
    public function mutations(
        string $accountId,
        ?string $dateFromYmd = null,
        ?string $dateToYmd = null
    ): array {
        $query = JournalLine::query()
            ->with('journal')
            ->where('account_id', $accountId)
            ->whereHas('journal', function ($q) use ($dateFromYmd, $dateToYmd) {
                $q->where('status', Journal::STATUS_POSTED)
                    ->whereNull('deleted_at');
                if ($dateFromYmd) {
                    $q->whereDate('journal_date', '>=', $dateFromYmd);
                }
                if ($dateToYmd) {
                    $q->whereDate('journal_date', '<=', $dateToYmd);
                }
            })
            ->get()
            ->sortBy(fn (JournalLine $line) => [
                optional($line->journal)->journal_date?->format('Y-m-d') ?? '',
                optional($line->journal)->journal_no ?? '',
                $line->line_no,
            ])
            ->values();

        $reconciledIds = $this->reconciledJournalLineIds($accountId);

        // Opening running balance before dateFrom
        $running = 0.0;
        if ($dateFromYmd) {
            $before = Carbon::parse($dateFromYmd)->subDay()->toDateString();
            $running = $this->bookBalance($accountId, $before);
        }

        $rows = [];
        foreach ($query as $line) {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);
            $running = round($running + $debit - $credit, 2);

            $rows[] = [
                'journal_line_id' => $line->id,
                'journal_id' => $line->journal_id,
                'journal_no' => $line->journal?->journal_no,
                'journal_date' => format_date_id($line->journal?->journal_date),
                'journal_date_ymd' => optional($line->journal?->journal_date)->toDateString(),
                'description' => $line->description ?: $line->journal?->description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $running,
                'is_reconciled' => in_array($line->id, $reconciledIds, true),
            ];
        }

        return $rows;
    }

    /**
     * Journal line IDs cleared in a completed reconciliation for this account.
     *
     * @return list<string>
     */
    public function reconciledJournalLineIds(string $accountId, ?string $exceptReconciliationId = null): array
    {
        $query = CashBankReconciliationLine::query()
            ->where('is_cleared', true)
            ->whereHas('reconciliation', function ($q) use ($accountId, $exceptReconciliationId) {
                $q->where('account_id', $accountId)
                    ->where('status', CashBankReconciliation::STATUS_COMPLETED)
                    ->whereNull('deleted_at');
                if ($exceptReconciliationId) {
                    $q->where('id', '!=', $exceptReconciliationId);
                }
            });

        return $query->pluck('journal_line_id')->all();
    }

    public function startOrResumeReconciliation(
        string $companyId,
        string $accountId,
        string $reconciliationDateInput,
        ?string $userId = null
    ): CashBankReconciliation {
        $account = $this->assertCashBankAccount($companyId, $accountId);
        $dateYmd = HelperController::parseDate($reconciliationDateInput);

        $existing = CashBankReconciliation::query()
            ->where('company_id', $companyId)
            ->where('account_id', $account->id)
            ->where('status', CashBankReconciliation::STATUS_DRAFT)
            ->whereNull('deleted_at')
            ->latest('created_at')
            ->first();

        if ($existing) {
            $existing->update([
                'reconciliation_date' => $dateYmd,
                'book_balance' => $this->bookBalance($account->id, $dateYmd),
                'updated_by' => $userId,
            ]);
            $this->syncReconciliationCandidates($existing);

            return $existing->fresh(['account', 'lines.journalLine.journal']);
        }

        return DB::transaction(function () use ($companyId, $account, $dateYmd, $userId) {
            $book = $this->bookBalance($account->id, $dateYmd);

            $recon = CashBankReconciliation::create([
                'company_id' => $companyId,
                'account_id' => $account->id,
                'reconciliation_date' => $dateYmd,
                'statement_balance' => 0,
                'book_balance' => $book,
                'cleared_balance' => 0,
                'difference' => round(0 - $book, 2),
                'status' => CashBankReconciliation::STATUS_DRAFT,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->syncReconciliationCandidates($recon);

            return $recon->fresh(['account', 'lines.journalLine.journal']);
        });
    }

    /**
     * Load uncleared posted mutations up to reconciliation date into draft lines.
     */
    public function syncReconciliationCandidates(CashBankReconciliation $recon): void
    {
        if (! $recon->isDraft()) {
            return;
        }

        $dateYmd = $recon->reconciliation_date->toDateString();
        $alreadyReconciled = $this->reconciledJournalLineIds($recon->account_id, $recon->id);
        $existingLineIds = $recon->lines()->pluck('journal_line_id')->all();

        $candidates = JournalLine::query()
            ->where('account_id', $recon->account_id)
            ->whereHas('journal', function ($q) use ($dateYmd) {
                $q->where('status', Journal::STATUS_POSTED)
                    ->whereNull('deleted_at')
                    ->whereDate('journal_date', '<=', $dateYmd);
            })
            ->whereNotIn('id', $alreadyReconciled)
            ->get();

        foreach ($candidates as $line) {
            if (in_array($line->id, $existingLineIds, true)) {
                continue;
            }

            CashBankReconciliationLine::create([
                'reconciliation_id' => $recon->id,
                'journal_line_id' => $line->id,
                'is_cleared' => false,
            ]);
        }

        // Drop lines that no longer qualify (e.g. date moved earlier) and not cleared
        $validIds = $candidates->pluck('id')->all();
        $recon->lines()
            ->where('is_cleared', false)
            ->whereNotIn('journal_line_id', $validIds)
            ->delete();
    }

    /**
     * @param  list<string>  $clearedJournalLineIds
     */
    public function saveDraft(
        CashBankReconciliation $recon,
        mixed $statementBalance,
        array $clearedJournalLineIds,
        ?string $notes = null,
        ?string $userId = null
    ): CashBankReconciliation {
        if (! $recon->isDraft()) {
            throw ValidationException::withMessages([
                'reconciliation' => 'Completed reconciliation cannot be edited.',
            ]);
        }

        $statement = round((float) (normalize_number_input($statementBalance) ?? 0), 2);
        $clearedSet = array_flip($clearedJournalLineIds);

        return DB::transaction(function () use ($recon, $statement, $clearedSet, $notes, $userId) {
            $this->syncReconciliationCandidates($recon);
            $recon->load('lines.journalLine');

            $clearedBalance = 0.0;
            foreach ($recon->lines as $line) {
                $isCleared = isset($clearedSet[$line->journal_line_id]);
                $line->update(['is_cleared' => $isCleared]);

                if ($isCleared && $line->journalLine) {
                    $clearedBalance += (float) $line->journalLine->debit - (float) $line->journalLine->credit;
                }
            }

            // Cleared balance should be opening-to-date of cleared only; for UI we use:
            // difference = statement - (book of uncleared adjustment). Standard:
            // Adjusted book = sum of cleared items' effect from zero + prior reconciled
            // Simpler practical formula:
            // book_balance = full book as of date
            // cleared_balance = sum(debit-credit) of cleared lines in this session
            // difference = statement_balance - (prior_reconciled_book + cleared_balance)
            // Even simpler for basic: difference = statement_balance - book_balance
            // and show cleared count separately.

            $book = $this->bookBalance($recon->account_id, $recon->reconciliation_date->toDateString());
            $clearedBalance = round($clearedBalance, 2);
            $difference = round($statement - $book, 2);

            $recon->update([
                'statement_balance' => $statement,
                'book_balance' => $book,
                'cleared_balance' => $clearedBalance,
                'difference' => $difference,
                'notes' => $notes,
                'updated_by' => $userId,
            ]);

            return $recon->fresh(['account', 'lines.journalLine.journal']);
        });
    }

    public function complete(CashBankReconciliation $recon, ?string $userId = null): CashBankReconciliation
    {
        if (! $recon->isDraft()) {
            throw ValidationException::withMessages([
                'reconciliation' => 'Reconciliation is already completed.',
            ]);
        }

        $recon->load('lines');
        $clearedCount = $recon->lines->where('is_cleared', true)->count();
        if ($clearedCount === 0) {
            throw ValidationException::withMessages([
                'reconciliation' => 'Clear at least one transaction before completing.',
            ]);
        }

        if (abs((float) $recon->difference) > 0.009) {
            throw ValidationException::withMessages([
                'reconciliation' => 'Statement balance must equal book balance before completing (difference must be 0).',
            ]);
        }

        $recon->update([
            'status' => CashBankReconciliation::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $userId,
            'updated_by' => $userId,
        ]);

        return $recon->fresh(['account', 'lines.journalLine.journal']);
    }

    public function reopen(CashBankReconciliation $recon, ?string $userId = null): CashBankReconciliation
    {
        if (! $recon->isCompleted()) {
            throw ValidationException::withMessages([
                'reconciliation' => 'Only completed reconciliations can be reopened.',
            ]);
        }

        // Only allow reopen of the latest completed for this account
        $latest = CashBankReconciliation::query()
            ->where('account_id', $recon->account_id)
            ->where('status', CashBankReconciliation::STATUS_COMPLETED)
            ->whereNull('deleted_at')
            ->orderByDesc('reconciliation_date')
            ->orderByDesc('completed_at')
            ->first();

        if ($latest && $latest->id !== $recon->id) {
            throw ValidationException::withMessages([
                'reconciliation' => 'Only the latest completed reconciliation can be reopened.',
            ]);
        }

        $recon->update([
            'status' => CashBankReconciliation::STATUS_DRAFT,
            'completed_at' => null,
            'completed_by' => null,
            'updated_by' => $userId,
        ]);

        return $recon->fresh(['account', 'lines.journalLine.journal']);
    }
}
