<?php

namespace App\Services\Accounting;

use App\Http\Controllers\Admin\HelperController;
use App\Models\Accounting\BeginningBalance;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\FiscalCalendar;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalAttachment;
use App\Models\Accounting\JournalLine;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JournalService
{
    public function eligibleAccounts(string $companyId): Collection
    {
        return ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_header', false)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type', 'normal_balance']);
    }

    public function suggestJournalNo(string $companyId, $journalDate, string $prefixCode = 'JE'): string
    {
        $year = Carbon::parse($journalDate)->format('Y');
        $prefix = $prefixCode.'-'.$year.'-';

        $last = Journal::query()
            ->where('company_id', $companyId)
            ->where('journal_no', 'like', $prefix.'%')
            ->orderByDesc('journal_no')
            ->value('journal_no');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create an already-posted opening balance journal from booked beginning balance lines.
     */
    public function createPostedOpeningBalanceJournal(
        BeginningBalance $balance,
        ?string $userId = null
    ): Journal {
        $balance->loadMissing(['lines.account', 'fiscalCalendar']);
        $calendar = $balance->fiscalCalendar;
        if (! $calendar) {
            throw ValidationException::withMessages([
                'balance' => 'Fiscal calendar not found for beginning balance.',
            ]);
        }

        $dateYmd = Carbon::parse($calendar->start_date)->toDateString();
        $resolved = $this->resolveOpenPeriod($balance->company_id, $dateYmd);

        $lines = [];
        foreach ($balance->lines as $line) {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $lines[] = [
                'account_id' => $line->account_id,
                'description' => $line->is_auto_balancing
                    ? 'Opening balance equity (auto)'
                    : ('Opening balance — '.($line->account?->code ?? '')),
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'balance' => 'Opening balance journal requires at least two non-zero lines.',
            ]);
        }

        return DB::transaction(function () use ($balance, $calendar, $resolved, $dateYmd, $lines, $userId) {
            $journal = Journal::create([
                'company_id' => $balance->company_id,
                'fiscal_calendar_id' => $calendar->id,
                'fiscal_period_id' => $resolved['period']->id,
                'journal_no' => $this->suggestJournalNo($balance->company_id, $dateYmd, 'OB'),
                'journal_date' => $dateYmd,
                'description' => 'Opening Balance FY '.$calendar->fiscal_year
                    .($balance->notes ? ' — '.$balance->notes : ''),
                'status' => Journal::STATUS_POSTED,
                'journal_type' => Journal::TYPE_OPENING_BALANCE,
                'posted_at' => now(),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $lineNo = 1;
            foreach ($lines as $line) {
                JournalLine::create([
                    'journal_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'line_no' => $lineNo++,
                    'description' => $line['description'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            return $journal->fresh(['lines.account']);
        });
    }

    public function deleteOpeningBalanceJournal(?Journal $journal, ?string $userId = null): void
    {
        if (! $journal) {
            return;
        }

        if ($journal->journal_type !== Journal::TYPE_OPENING_BALANCE) {
            throw ValidationException::withMessages([
                'journal' => 'Linked journal is not an opening balance journal.',
            ]);
        }

        $journal->deleted_by = $userId;
        $journal->save();
        $journal->delete();
    }

    /**
     * @return array{calendar: FiscalCalendar, period: FiscalPeriod}
     */
    public function resolveOpenPeriod(string $companyId, string $journalDateYmd): array
    {
        $date = Carbon::parse($journalDateYmd)->toDateString();

        $calendar = FiscalCalendar::query()
            ->where('company_id', $companyId)
            ->where('is_closed', false)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if (! $calendar) {
            throw ValidationException::withMessages([
                'journal_date' => 'No open fiscal calendar covers this journal date.',
            ]);
        }

        $period = FiscalPeriod::query()
            ->where('fiscal_calendar_id', $calendar->id)
            ->where('status', FiscalPeriod::STATUS_OPEN)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'journal_date' => 'No open fiscal period covers this journal date. Open the period first.',
            ]);
        }

        return ['calendar' => $calendar, 'period' => $period];
    }

    /**
     * @param  array<int, array{account_id?: string, description?: string, debit?: mixed, credit?: mixed}>  $lines
     * @param  list<UploadedFile>  $files
     */
    public function create(array $payload, array $lines, array $files = [], ?string $userId = null): Journal
    {
        $dateYmd = $this->normalizeDate($payload['journal_date']);
        $resolved = $this->resolveOpenPeriod($payload['company_id'], $dateYmd);
        $normalizedLines = $this->normalizeLines($lines);

        return DB::transaction(function () use ($payload, $normalizedLines, $files, $userId, $dateYmd, $resolved) {
            $journal = Journal::create([
                'company_id' => $payload['company_id'],
                'fiscal_calendar_id' => $resolved['calendar']->id,
                'fiscal_period_id' => $resolved['period']->id,
                'journal_no' => $this->suggestJournalNo($payload['company_id'], $dateYmd),
                'journal_date' => $dateYmd,
                'description' => $payload['description'] ?? null,
                'status' => Journal::STATUS_DRAFT,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->replaceLines($journal, $normalizedLines, $userId);
            $this->storeAttachments($journal, $files, $userId);

            return $journal->fresh(['lines.account', 'attachments', 'company', 'fiscalPeriod']);
        });
    }

    /**
     * @param  array<int, array{account_id?: string, description?: string, debit?: mixed, credit?: mixed}>  $lines
     * @param  list<UploadedFile>  $files
     */
    public function update(Journal $journal, array $payload, array $lines, array $files = [], ?string $userId = null): Journal
    {
        $this->assertEditable($journal);

        $dateYmd = $this->normalizeDate($payload['journal_date']);
        $resolved = $this->resolveOpenPeriod($journal->company_id, $dateYmd);
        $normalizedLines = $this->normalizeLines($lines);

        return DB::transaction(function () use ($journal, $payload, $normalizedLines, $files, $userId, $dateYmd, $resolved) {
            $journal->update([
                'fiscal_calendar_id' => $resolved['calendar']->id,
                'fiscal_period_id' => $resolved['period']->id,
                'journal_date' => $dateYmd,
                'description' => $payload['description'] ?? null,
                'updated_by' => $userId,
            ]);

            $this->replaceLines($journal, $normalizedLines, $userId);
            $this->storeAttachments($journal, $files, $userId);

            return $journal->fresh(['lines.account', 'attachments', 'company', 'fiscalPeriod']);
        });
    }

    public function post(Journal $journal, ?string $userId = null): Journal
    {
        $this->assertEditable($journal);
        $journal->load('lines');

        if ($journal->lines->count() < 2) {
            throw ValidationException::withMessages([
                'lines' => 'Journal must have at least two lines.',
            ]);
        }

        $debit = round($journal->totalDebit(), 2);
        $credit = round($journal->totalCredit(), 2);

        if ($debit <= 0 && $credit <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'Journal amounts cannot be empty.',
            ]);
        }

        if (abs($debit - $credit) > 0.009) {
            throw ValidationException::withMessages([
                'lines' => sprintf(
                    'Journal is not balanced (Debit %s vs Credit %s).',
                    number_format($debit, 2),
                    number_format($credit, 2)
                ),
            ]);
        }

        // Re-validate period still open for date
        $this->resolveOpenPeriod($journal->company_id, $journal->journal_date->toDateString());

        $journal->update([
            'status' => Journal::STATUS_POSTED,
            'posted_at' => now(),
            'posted_by' => $userId,
            'updated_by' => $userId,
        ]);

        return $journal->fresh(['lines.account', 'attachments', 'company', 'fiscalPeriod']);
    }

    public function unpost(Journal $journal, ?string $userId = null): Journal
    {
        if (! $journal->isPosted()) {
            throw ValidationException::withMessages([
                'journal' => 'Only posted journals can be unposted.',
            ]);
        }

        // Period must still be open to unpost
        if ($journal->fiscal_period_id) {
            $period = FiscalPeriod::find($journal->fiscal_period_id);
            if ($period && $period->status !== FiscalPeriod::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'journal' => 'Cannot unpost: fiscal period is not open.',
                ]);
            }
        }

        $journal->update([
            'status' => Journal::STATUS_DRAFT,
            'posted_at' => null,
            'posted_by' => null,
            'updated_by' => $userId,
        ]);

        return $journal->fresh(['lines.account', 'attachments', 'company', 'fiscalPeriod']);
    }

    public function deleteDraft(Journal $journal, ?string $userId = null): void
    {
        $this->assertEditable($journal);

        DB::transaction(function () use ($journal, $userId) {
            foreach ($journal->attachments as $attachment) {
                $this->deleteAttachmentFile($attachment);
            }

            $journal->deleted_by = $userId;
            $journal->save();
            $journal->delete();
        });
    }

    public function deleteAttachment(JournalAttachment $attachment, Journal $journal): void
    {
        $this->assertEditable($journal);

        $this->deleteAttachmentFile($attachment);
        $attachment->delete();
    }

    protected function deleteAttachmentFile(JournalAttachment $attachment): void
    {
        if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    protected function storeAttachments(Journal $journal, array $files, ?string $userId): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('accounting/journals/'.$journal->id, $filename, 'public');

            JournalAttachment::create([
                'journal_id' => $journal->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize() ?: 0,
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * @param  array<int, array{account_id: string, description?: ?string, debit: float, credit: float}>  $lines
     */
    protected function replaceLines(Journal $journal, array $lines, ?string $userId): void
    {
        $journal->lines()->delete();

        $lineNo = 1;
        foreach ($lines as $line) {
            JournalLine::create([
                'journal_id' => $journal->id,
                'account_id' => $line['account_id'],
                'line_no' => $lineNo++,
                'description' => $line['description'] ?? null,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    /**
     * @param  array<int, array{account_id?: string, description?: string, debit?: mixed, credit?: mixed}>  $lines
     * @return array<int, array{account_id: string, description: ?string, debit: float, credit: float}>
     */
    protected function normalizeLines(array $lines): array
    {
        $normalized = [];
        $usedAccounts = [];

        foreach ($lines as $line) {
            $accountId = $line['account_id'] ?? null;
            if (! $accountId) {
                continue;
            }

            if (isset($usedAccounts[$accountId])) {
                throw ValidationException::withMessages([
                    'lines' => 'Each account can only be used once in a journal entry.',
                ]);
            }

            $debit = round(max(0, (float) (normalize_number_input($line['debit'] ?? 0) ?? 0)), 2);
            $credit = round(max(0, (float) (normalize_number_input($line['credit'] ?? 0) ?? 0)), 2);

            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages([
                    'lines' => 'Each line may have either debit or credit, not both.',
                ]);
            }

            $usedAccounts[$accountId] = true;

            $normalized[] = [
                'account_id' => $accountId,
                'description' => isset($line['description']) ? trim((string) $line['description']) ?: null : null,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (count($normalized) === 0) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one journal line with an amount.',
            ]);
        }

        return $normalized;
    }

    protected function normalizeDate(string $date): string
    {
        // Accept d/m/Y or Y-m-d
        return HelperController::parseDate($date);
    }

    public function assertEditable(Journal $journal): void
    {
        if ($journal->isPosted()) {
            throw ValidationException::withMessages([
                'journal' => 'Posted journal cannot be edited. Unpost first.',
            ]);
        }
    }
}
