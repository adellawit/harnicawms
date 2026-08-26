<?php

namespace App\Services\Ai\Actions;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\Journal;
use App\Services\Accounting\JournalService;
use App\Services\Ai\AgentContext;
use Illuminate\Validation\ValidationException;

/**
 * Draf jurnal dari chat lewat JournalService. Posting hanya jika seimbang.
 *
 * Called from AgentRecordActionService for entity=journal create/post.
 */
class JournalChatService
{
    public function __construct(
        protected JournalService $journals,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function createDraft(array $arguments, AgentContext $context, bool $commit = true): array
    {
        if (! $context->companyId) {
            return [
                'success' => false,
                'message' => 'Company aktif belum dipilih. Pilih cabang/perusahaan di profil dulu.',
            ];
        }

        $description = ChatFields::string(
            $arguments,
            ['description', 'deskripsi', 'keterangan', 'notes'],
            $arguments['description'] ?? $arguments['name'] ?? null,
        );
        $rawLines = ChatFields::array($arguments, ['lines', 'baris', 'entries']);

        $missing = [];
        $questions = [];

        if ($description === null) {
            $missing[] = 'description';
            $questions[] = 'Keterangan jurnalnya apa?';
        }

        if ($rawLines === null) {
            $missing[] = 'lines';
            $questions[] = 'Isi minimal dua baris (akun + debit/kredit). Contoh: akun kas debit 10000 dan akun pendapatan kredit 10000.';
        }

        if ($missing !== []) {
            return ChatFields::missing($missing, implode(' ', $questions));
        }

        $lines = $this->resolveLines($rawLines, $context);
        if (($lines['error'] ?? null) !== null) {
            return [
                'success' => false,
                'missing' => ['lines'],
                'message' => (string) $lines['error'],
            ];
        }

        $normalized = $lines['lines'];
        $summary = 'Draf jurnal "'.$description.'" ('.count($normalized).' baris). Belum diposting.';

        if (! $commit) {
            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'journal_draft',
                'title' => 'Buat draf jurnal?',
                'body' => $summary,
                'confirm_label' => 'Buat draf',
                'cancel_label' => 'Batal',
                'message' => $summary.' Konfirmasi dulu di kartu.',
            ];
        }

        try {
            $journal = $this->journals->create(
                [
                    'company_id' => $context->companyId,
                    'journal_date' => now()->toDateString(),
                    'description' => $description,
                ],
                $normalized,
                [],
                $context->user->id,
            );
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => $this->firstError($e) ?? 'Jurnal tidak valid. Periksa baris debit/kredit.',
            ];
        }

        $item = $this->serialize($journal);

        return [
            'success' => true,
            'applied' => true,
            'entity' => 'journal',
            'item' => $item,
            'items' => [$item],
            'message' => 'Draf jurnal '.$journal->journal_no.' tersimpan. Posting hanya setelah seimbang, lewat konfirmasi di chat atau modul Journal Entry.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function post(array $arguments, AgentContext $context, bool $commit = true): array
    {
        $journal = $this->findJournal($arguments, $context);
        if ($journal === null) {
            return [
                'success' => false,
                'message' => 'Jurnal tidak ditemukan.',
            ];
        }

        if ($journal->isPosted()) {
            return [
                'success' => true,
                'applied' => false,
                'already_exists' => true,
                'message' => 'Jurnal '.$journal->journal_no.' sudah diposting.',
            ];
        }

        $summary = 'Posting jurnal '.$journal->journal_no.'. Hanya berhasil jika debit = kredit.';

        if (! $commit) {
            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'journal_post',
                'title' => 'Posting jurnal?',
                'body' => $summary,
                'confirm_label' => 'Posting',
                'cancel_label' => 'Batal',
                'message' => $summary.' Konfirmasi dulu di kartu.',
            ];
        }

        try {
            $posted = $this->journals->post($journal, $context->user->id);
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => $this->firstError($e) ?? 'Jurnal tidak seimbang, jadi tidak diposting.',
            ];
        }

        $item = $this->serialize($posted);

        return [
            'success' => true,
            'applied' => true,
            'entity' => 'journal',
            'item' => $item,
            'items' => [$item],
            'message' => 'Jurnal '.$posted->journal_no.' berhasil diposting.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Journal $journal): array
    {
        $label = (string) $journal->journal_no;

        return [
            'id' => $journal->id,
            'name' => $label,
            'label' => $label,
            'code' => $label,
            'status' => $journal->status,
            'description' => $journal->description,
        ];
    }

    /**
     * @param  array<int, mixed>  $rawLines
     * @return array{lines?: list<array<string, mixed>>, error?: string}
     */
    protected function resolveLines(array $rawLines, AgentContext $context): array
    {
        $lines = [];

        foreach ($rawLines as $row) {
            if (! is_array($row)) {
                continue;
            }

            $accountNeedle = trim((string) ($row['account'] ?? $row['account_code'] ?? $row['code'] ?? $row['akun'] ?? ''));
            $accountId = trim((string) ($row['account_id'] ?? ''));

            $account = $accountId !== ''
                ? ChartOfAccount::query()->where('company_id', $context->companyId)->find($accountId)
                : $this->findAccount($accountNeedle, $context);

            if ($account === null) {
                return ['error' => 'Akun "'.($accountNeedle !== '' ? $accountNeedle : $accountId).'" tidak ditemukan. Pakai kode atau nama COA.'];
            }

            $lines[] = [
                'account_id' => $account->id,
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'debit' => $row['debit'] ?? $row['debet'] ?? 0,
                'credit' => $row['credit'] ?? $row['kredit'] ?? 0,
            ];
        }

        if (count($lines) < 1) {
            return ['error' => 'Isi minimal satu baris jurnal dengan akun dan nominal.'];
        }

        return ['lines' => $lines];
    }

    protected function findAccount(string $needle, AgentContext $context): ?ChartOfAccount
    {
        if ($needle === '') {
            return null;
        }

        return ChartOfAccount::query()
            ->where('company_id', $context->companyId)
            ->where('is_active', true)
            ->where('is_header', false)
            ->where(function ($q) use ($needle) {
                $q->where('code', 'ilike', $needle)->orWhere('name', 'ilike', $needle);
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function findJournal(array $arguments, AgentContext $context): ?Journal
    {
        $id = trim((string) ($arguments['id'] ?? ''));
        $needle = trim((string) ($arguments['query'] ?? $arguments['name'] ?? $arguments['code'] ?? ''));

        $query = Journal::query()->when(
            $context->companyId,
            fn ($q) => $q->where('company_id', $context->companyId),
        );

        if ($id !== '') {
            return $query->find($id);
        }

        if ($needle === '') {
            return null;
        }

        return $query->where('journal_no', 'ilike', $needle)->first();
    }

    protected function firstError(ValidationException $e): ?string
    {
        $flat = collect($e->errors())->flatten()->filter()->values();

        return $flat->first() !== null ? (string) $flat->first() : null;
    }
}
