<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountMapping;
use App\Models\Accounting\BeginningBalance;
use App\Models\Accounting\BeginningBalanceLine;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\FiscalCalendar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BeginningBalanceService
{
    public const OPENING_EQUITY_MAPPING_KEY = 'opening_balance_equity';

    public function __construct(
        private readonly JournalService $journalService
    ) {}

    /**
     * Balance-sheet detail accounts eligible for beginning balance.
     */
    public function eligibleAccounts(string $companyId): Collection
    {
        return ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_header', false)
            ->whereIn('account_type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get();
    }

    public function ensureDraftForCalendar(FiscalCalendar $calendar, ?string $userId = null, bool $suggestFromPrevious = true): BeginningBalance
    {
        $existing = BeginningBalance::query()
            ->where('fiscal_calendar_id', $calendar->id)
            ->first();

        if ($existing) {
            $this->syncAccountLines($existing, $userId);

            return $existing->fresh(['lines.account', 'fiscalCalendar', 'company']);
        }

        return DB::transaction(function () use ($calendar, $userId, $suggestFromPrevious) {
            $balance = BeginningBalance::create([
                'company_id' => $calendar->company_id,
                'fiscal_calendar_id' => $calendar->id,
                'status' => BeginningBalance::STATUS_DRAFT,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $suggested = $suggestFromPrevious
                ? $this->suggestedAmountsFromPreviousYear($calendar)
                : collect();

            $this->createLinesForAccounts($balance, $suggested, $userId);

            return $balance->fresh(['lines.account', 'fiscalCalendar', 'company']);
        });
    }

    /**
     * @param  Collection<string, array{debit: float, credit: float}>  $suggested
     */
    protected function createLinesForAccounts(BeginningBalance $balance, Collection $suggested, ?string $userId): void
    {
        foreach ($this->eligibleAccounts($balance->company_id) as $account) {
            $row = $suggested->get($account->id, ['debit' => 0.0, 'credit' => 0.0]);
            $debit = round((float) ($row['debit'] ?? 0), 2);
            $credit = round((float) ($row['credit'] ?? 0), 2);
            $hasAmount = $debit > 0 || $credit > 0;

            BeginningBalanceLine::create([
                'beginning_balance_id' => $balance->id,
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
                'source' => $hasAmount ? BeginningBalanceLine::SOURCE_SUGGESTED : BeginningBalanceLine::SOURCE_MANUAL,
                'is_auto_balancing' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    /**
     * Add missing COA lines without wiping existing amounts (draft only).
     */
    public function syncAccountLines(BeginningBalance $balance, ?string $userId = null): void
    {
        if (! $balance->isDraft()) {
            return;
        }

        $existingIds = $balance->lines()->pluck('account_id')->all();
        $eligible = $this->eligibleAccounts($balance->company_id);

        foreach ($eligible as $account) {
            if (in_array($account->id, $existingIds, true)) {
                continue;
            }

            BeginningBalanceLine::create([
                'beginning_balance_id' => $balance->id,
                'account_id' => $account->id,
                'debit' => 0,
                'credit' => 0,
                'source' => BeginningBalanceLine::SOURCE_MANUAL,
                'is_auto_balancing' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    /**
     * @return Collection<string, array{debit: float, credit: float}>
     */
    public function suggestedAmountsFromPreviousYear(FiscalCalendar $calendar): Collection
    {
        $previous = FiscalCalendar::query()
            ->where('company_id', $calendar->company_id)
            ->where('fiscal_year', '<', $calendar->fiscal_year)
            ->orderByDesc('fiscal_year')
            ->first();

        if (! $previous) {
            return collect();
        }

        $prevBalance = BeginningBalance::query()
            ->where('fiscal_calendar_id', $previous->id)
            ->where('status', BeginningBalance::STATUS_POSTED)
            ->with('lines')
            ->first();

        if (! $prevBalance) {
            return collect();
        }

        return $prevBalance->lines
            ->filter(fn (BeginningBalanceLine $line) => ! $line->is_auto_balancing)
            ->mapWithKeys(fn (BeginningBalanceLine $line) => [
                $line->account_id => [
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ],
            ]);
    }

    public function applySuggestionFromPrevious(BeginningBalance $balance, ?string $userId = null): BeginningBalance
    {
        $this->assertEditable($balance);

        $calendar = $balance->fiscalCalendar;
        $suggested = $this->suggestedAmountsFromPreviousYear($calendar);

        if ($suggested->isEmpty()) {
            throw ValidationException::withMessages([
                'balance' => 'No posted beginning balance found in a previous fiscal year to suggest from.',
            ]);
        }

        return DB::transaction(function () use ($balance, $suggested, $userId) {
            foreach ($balance->lines as $line) {
                if ($line->is_auto_balancing) {
                    $line->update([
                        'debit' => 0,
                        'credit' => 0,
                        'source' => BeginningBalanceLine::SOURCE_MANUAL,
                        'is_auto_balancing' => false,
                        'updated_by' => $userId,
                    ]);

                    continue;
                }

                $row = $suggested->get($line->account_id);
                if (! $row) {
                    continue;
                }

                $debit = round((float) $row['debit'], 2);
                $credit = round((float) $row['credit'], 2);

                $line->update([
                    'debit' => $debit,
                    'credit' => $credit,
                    'source' => ($debit > 0 || $credit > 0)
                        ? BeginningBalanceLine::SOURCE_SUGGESTED
                        : BeginningBalanceLine::SOURCE_MANUAL,
                    'updated_by' => $userId,
                ]);
            }

            $balance->update(['updated_by' => $userId]);

            return $balance->fresh(['lines.account', 'fiscalCalendar', 'company']);
        });
    }

    /**
     * @param  array<int, array{account_id: string, debit?: mixed, credit?: mixed}>  $lines
     */
    public function saveDraft(BeginningBalance $balance, array $lines, ?string $notes = null, ?string $userId = null): BeginningBalance
    {
        $this->assertEditable($balance);

        return DB::transaction(function () use ($balance, $lines, $notes, $userId) {
            $byAccount = collect($lines)->keyBy('account_id');

            foreach ($balance->lines as $line) {
                if (! $byAccount->has($line->account_id)) {
                    continue;
                }

                $input = $byAccount->get($line->account_id);
                $debit = round(max(0, (float) (normalize_number_input($input['debit'] ?? 0) ?? 0)), 2);
                $credit = round(max(0, (float) (normalize_number_input($input['credit'] ?? 0) ?? 0)), 2);

                if ($debit > 0 && $credit > 0) {
                    throw ValidationException::withMessages([
                        'lines' => 'Each account may have either debit or credit, not both. ('.$line->account?->code.')',
                    ]);
                }

                $line->update([
                    'debit' => $debit,
                    'credit' => $credit,
                    'source' => BeginningBalanceLine::SOURCE_MANUAL,
                    'is_auto_balancing' => false,
                    'updated_by' => $userId,
                ]);
            }

            // Clear previous auto-balance rows' flags after manual save
            $balance->lines()
                ->where('is_auto_balancing', true)
                ->update([
                    'is_auto_balancing' => false,
                    'source' => BeginningBalanceLine::SOURCE_MANUAL,
                    'updated_by' => $userId,
                ]);

            $balance->update([
                'notes' => $notes,
                'updated_by' => $userId,
            ]);

            return $balance->fresh(['lines.account', 'fiscalCalendar', 'company']);
        });
    }

    public function book(BeginningBalance $balance, ?string $userId = null): BeginningBalance
    {
        $this->assertEditable($balance);

        return DB::transaction(function () use ($balance, $userId) {
            $balance->load('lines.account');
            $this->applyAutoBalancing($balance, $userId);
            $balance->load('lines');

            $debit = round($balance->totalDebit(), 2);
            $credit = round($balance->totalCredit(), 2);

            if (abs($debit - $credit) > 0.009) {
                throw ValidationException::withMessages([
                    'balance' => sprintf(
                        'Beginning balance is not balanced after auto-balancing (Debit %s vs Credit %s). Check Opening Balance Equity mapping.',
                        number_format($debit, 2),
                        number_format($credit, 2)
                    ),
                ]);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw ValidationException::withMessages([
                    'balance' => 'Cannot book an empty beginning balance. Enter at least one amount.',
                ]);
            }

            $journal = $this->journalService->createPostedOpeningBalanceJournal($balance, $userId);

            $balance->update([
                'status' => BeginningBalance::STATUS_POSTED,
                'journal_id' => $journal->id,
                'posted_at' => now(),
                'posted_by' => $userId,
                'updated_by' => $userId,
            ]);

            return $balance->fresh(['lines.account', 'fiscalCalendar', 'company', 'journal']);
        });
    }

    /**
     * Backfill journal for already-posted beginning balance (before journal link existed).
     */
    public function ensurePostedJournal(BeginningBalance $balance, ?string $userId = null): BeginningBalance
    {
        if (! $balance->isPosted() || $balance->journal_id) {
            return $balance;
        }

        return DB::transaction(function () use ($balance, $userId) {
            $balance->load('lines.account', 'fiscalCalendar');
            $journal = $this->journalService->createPostedOpeningBalanceJournal($balance, $userId);
            $balance->update([
                'journal_id' => $journal->id,
                'updated_by' => $userId,
            ]);

            return $balance->fresh(['lines.account', 'fiscalCalendar', 'company', 'journal']);
        });
    }

    public function unpost(BeginningBalance $balance, ?string $userId = null): BeginningBalance
    {
        if (! $balance->isPosted()) {
            throw ValidationException::withMessages([
                'balance' => 'Only posted beginning balances can be unposted.',
            ]);
        }

        if ($this->hasJournalMutations($balance)) {
            throw ValidationException::withMessages([
                'balance' => 'Cannot unpost: other journal mutations already exist for this fiscal year.',
            ]);
        }

        return DB::transaction(function () use ($balance, $userId) {
            $journal = $balance->journal_id
                ? \App\Models\Accounting\Journal::withTrashed()->find($balance->journal_id)
                : null;

            if ($journal && ! $journal->trashed()) {
                $this->journalService->deleteOpeningBalanceJournal($journal, $userId);
            }

            $balance->update([
                'status' => BeginningBalance::STATUS_DRAFT,
                'journal_id' => null,
                'posted_at' => null,
                'posted_by' => null,
                'updated_by' => $userId,
            ]);

            return $balance->fresh(['lines.account', 'fiscalCalendar', 'company']);
        });
    }

    protected function applyAutoBalancing(BeginningBalance $balance, ?string $userId): void
    {
        // Reset previous auto-balance amounts on equity account
        foreach ($balance->lines as $line) {
            if ($line->is_auto_balancing) {
                $line->update([
                    'debit' => 0,
                    'credit' => 0,
                    'is_auto_balancing' => false,
                    'source' => BeginningBalanceLine::SOURCE_MANUAL,
                    'updated_by' => $userId,
                ]);
            }
        }

        $balance->unsetRelation('lines');
        $balance->load('lines');

        $diff = round($balance->totalDebit() - $balance->totalCredit(), 2);
        if (abs($diff) < 0.009) {
            return;
        }

        $equityAccountId = $this->resolveOpeningEquityAccountId($balance->company_id);

        $line = $balance->lines->firstWhere('account_id', $equityAccountId);
        if (! $line) {
            $line = BeginningBalanceLine::create([
                'beginning_balance_id' => $balance->id,
                'account_id' => $equityAccountId,
                'debit' => 0,
                'credit' => 0,
                'source' => BeginningBalanceLine::SOURCE_AUTO_BALANCE,
                'is_auto_balancing' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        // Debit > Credit → need more credit on equity (typical: assets without full liability/equity)
        // Credit > Debit → need more debit on equity
        $debit = 0.0;
        $credit = 0.0;
        if ($diff > 0) {
            $credit = $diff;
        } else {
            $debit = abs($diff);
        }

        $line->update([
            'debit' => $debit,
            'credit' => $credit,
            'source' => BeginningBalanceLine::SOURCE_AUTO_BALANCE,
            'is_auto_balancing' => true,
            'updated_by' => $userId,
        ]);
    }

    public function resolveOpeningEquityAccountId(string $companyId): string
    {
        $mapping = AccountMapping::query()
            ->where('company_id', $companyId)
            ->where('mapping_key', self::OPENING_EQUITY_MAPPING_KEY)
            ->first();

        if ($mapping?->account_id) {
            return $mapping->account_id;
        }

        // Fallback: first active equity detail account
        $fallback = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_header', false)
            ->where('account_type', 'equity')
            ->orderBy('code')
            ->first();

        if (! $fallback) {
            throw ValidationException::withMessages([
                'balance' => 'Map "Opening Balance Equity" in Account Mapping (or create an equity account) before booking.',
            ]);
        }

        return $fallback->id;
    }

    public function assertEditable(BeginningBalance $balance): void
    {
        if ($balance->isPosted()) {
            throw ValidationException::withMessages([
                'balance' => 'Posted beginning balance cannot be edited. Unpost first (if no journals exist).',
            ]);
        }

        if ($this->hasJournalMutations($balance)) {
            throw ValidationException::withMessages([
                'balance' => 'Beginning balance cannot be edited because journal mutations already exist for this fiscal year.',
            ]);
        }
    }

    /**
     * True if there are journals for this FY other than the linked opening-balance journal.
     */
    public function hasJournalMutations(BeginningBalance $balance): bool
    {
        if (! Schema::hasTable('accounting.journals')) {
            return false;
        }

        $query = DB::table('accounting.journals')
            ->where('company_id', $balance->company_id)
            ->where('fiscal_calendar_id', $balance->fiscal_calendar_id)
            ->whereNull('deleted_at');

        if ($balance->journal_id) {
            $query->where('id', '!=', $balance->journal_id);
        } else {
            // Exclude any leftover opening-balance journals for this calendar
            $query->where(function ($q) {
                $q->whereNull('journal_type')
                    ->orWhere('journal_type', '!=', 'opening_balance');
            });
        }

        return $query->exists();
    }
}
