<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalAttachment;
use App\Models\BusinessUnit;
use App\Services\Accounting\JournalService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalService $journalService
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
        $status = $request->get('status', 'all');
        $search = trim((string) $request->get('q', ''));
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $isFilter = $status !== 'all' || $search !== '' || $dateFrom || $dateTo;

        $journals = collect();
        if ($companyId) {
            $this->assertCompanyAccess($companyId);

            $query = Journal::query()
                ->withCount(['lines', 'attachments'])
                ->with(['fiscalPeriod:id,code,name'])
                ->where('company_id', $companyId);

            if ($status === 'draft') {
                $query->where('status', Journal::STATUS_DRAFT);
            } elseif ($status === 'posted') {
                $query->where('status', Journal::STATUS_POSTED);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('journal_no', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            }

            if ($dateFrom) {
                $query->whereDate('journal_date', '>=', HelperController::parseDate($dateFrom));
            }
            if ($dateTo) {
                $query->whereDate('journal_date', '<=', HelperController::parseDate($dateTo));
            }

            $journals = $query->orderByDesc('journal_date')->orderByDesc('journal_no')->paginate(25)->withQueryString();
        }

        return view('admin.finance.journal-entry.index', compact(
            'companies',
            'companyId',
            'journals',
            'status',
            'search',
            'dateFrom',
            'dateTo',
            'isFilter'
        ));
    }

    public function insertView(Request $request)
    {
        $companies = $this->companiesQuery()->orderBy('name')->get();
        $companyId = $request->get('company_id') ?: $companies->first()?->id;
        $accounts = $companyId ? $this->journalService->eligibleAccounts($companyId) : collect();
        $journalDate = format_date_id(now());
        $journalNo = $companyId
            ? $this->journalService->suggestJournalNo($companyId, now()->toDateString())
            : '';

        return view('admin.finance.journal-entry.form', [
            'mode' => 'insert',
            'journal' => null,
            'companies' => $companies,
            'companyId' => $companyId,
            'accounts' => $accounts,
            'journalDate' => $journalDate,
            'journalNo' => $journalNo,
            'canEdit' => true,
        ]);
    }

    public function editView(string $id)
    {
        $journal = Journal::with(['lines.account', 'attachments', 'company', 'fiscalPeriod'])
            ->findOrFail($id);
        $this->assertCompanyAccess($journal->company_id);

        $companies = $this->companiesQuery()->orderBy('name')->get();
        $accounts = $this->journalService->eligibleAccounts($journal->company_id);

        return view('admin.finance.journal-entry.form', [
            'mode' => 'edit',
            'journal' => $journal,
            'companies' => $companies,
            'companyId' => $journal->company_id,
            'accounts' => $accounts,
            'journalDate' => format_date_id($journal->journal_date),
            'journalNo' => $journal->journal_no,
            'canEdit' => $journal->isDraft(),
        ]);
    }

    public function saveData(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|uuid',
            'company_id' => 'required|uuid',
            'journal_date' => 'required|string',
            'description' => 'nullable|string|max:2000',
            'lines' => 'nullable|array',
            'lines.*.account_id' => 'nullable|uuid',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.debit' => 'nullable',
            'lines.*.credit' => 'nullable',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'action' => 'nullable|in:save,post',
        ]);

        $isUpdate = ! empty($validated['id']);
        $canCreate = session('permissions.Journal Entry.is_create', false) == 1;
        $canUpdate = session('permissions.Journal Entry.is_update', false) == 1;
        if (($isUpdate && ! $canUpdate) || (! $isUpdate && ! $canCreate)) {
            abort(403);
        }

        $this->assertCompanyAccess($validated['company_id']);

        $files = $request->file('attachments', []) ?: [];
        if (! is_array($files)) {
            $files = [$files];
        }

        $payload = [
            'company_id' => $validated['company_id'],
            'journal_date' => $validated['journal_date'],
            'description' => $validated['description'] ?? null,
        ];

        if (! empty($validated['id'])) {
            $journal = Journal::findOrFail($validated['id']);
            $this->assertCompanyAccess($journal->company_id);
            $journal = $this->journalService->update(
                $journal,
                $payload,
                $validated['lines'] ?? [],
                $files,
                auth('web')->id()
            );
        } else {
            $journal = $this->journalService->create(
                $payload,
                $validated['lines'] ?? [],
                $files,
                auth('web')->id()
            );
        }

        if (($validated['action'] ?? 'save') === 'post') {
            $this->journalService->post($journal, auth('web')->id());

            return redirect()
                ->route('finance.journal-entry.index.view', ['company_id' => $journal->company_id])
                ->with('success', 'Journal posted: '.$journal->journal_no);
        }

        return redirect()
            ->route('finance.journal-entry.edit.view', $journal->id)
            ->with('success', 'Journal saved as draft.');
    }

    public function postData(Request $request)
    {
        $validated = $request->validate(['id' => 'required|uuid']);
        $journal = Journal::with('lines')->findOrFail($validated['id']);
        $this->assertCompanyAccess($journal->company_id);

        $this->journalService->post($journal, auth('web')->id());

        return redirect()
            ->route('finance.journal-entry.index.view', ['company_id' => $journal->company_id])
            ->with('success', 'Journal posted: '.$journal->journal_no);
    }

    public function unpostData(Request $request)
    {
        $validated = $request->validate(['id' => 'required|uuid']);
        $journal = Journal::findOrFail($validated['id']);
        $this->assertCompanyAccess($journal->company_id);

        $this->journalService->unpost($journal, auth('web')->id());

        return redirect()
            ->route('finance.journal-entry.edit.view', $journal->id)
            ->with('success', 'Journal unposted. You can edit again.');
    }

    public function deleteData(Request $request)
    {
        $validated = $request->validate(['id' => 'required|uuid']);
        $journal = Journal::with('attachments')->findOrFail($validated['id']);
        $this->assertCompanyAccess($journal->company_id);
        $companyId = $journal->company_id;

        $this->journalService->deleteDraft($journal, auth('web')->id());

        return redirect()
            ->route('finance.journal-entry.index.view', ['company_id' => $companyId])
            ->with('success', 'Draft journal deleted.');
    }

    public function deleteAttachment(Request $request)
    {
        $validated = $request->validate([
            'journal_id' => 'required|uuid',
            'attachment_id' => 'required|uuid',
        ]);

        $journal = Journal::findOrFail($validated['journal_id']);
        $this->assertCompanyAccess($journal->company_id);

        $attachment = JournalAttachment::where('journal_id', $journal->id)
            ->where('id', $validated['attachment_id'])
            ->firstOrFail();

        $this->journalService->deleteAttachment($attachment, $journal);

        return redirect()
            ->route('finance.journal-entry.edit.view', $journal->id)
            ->with('success', 'Attachment removed.');
    }

    public function accountsData(Request $request)
    {
        $companyId = $request->get('company_id');
        if (! $companyId) {
            return response()->json([]);
        }
        $this->assertCompanyAccess($companyId);

        return response()->json(
            $this->journalService->eligibleAccounts($companyId)->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'label' => $a->code.' — '.$a->name,
            ])->values()
        );
    }

    public function suggestNoData(Request $request)
    {
        $companyId = $request->get('company_id');
        $date = $request->get('journal_date') ?: format_date_id(now());
        if (! $companyId) {
            return response()->json(['journal_no' => '']);
        }
        $this->assertCompanyAccess($companyId);
        $ymd = HelperController::parseDate($date);

        return response()->json([
            'journal_no' => $this->journalService->suggestJournalNo($companyId, $ymd),
        ]);
    }
}
