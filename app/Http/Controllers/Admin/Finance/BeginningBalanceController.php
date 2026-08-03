<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BeginningBalance;
use App\Models\Accounting\FiscalCalendar;
use App\Models\BusinessUnit;
use App\Services\Accounting\BeginningBalanceService;
use Illuminate\Http\Request;

class BeginningBalanceController extends Controller
{
    public function __construct(
        private readonly BeginningBalanceService $service
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

        $calendars = collect();
        if ($companyId) {
            $this->assertCompanyAccess($companyId);

            $calendars = FiscalCalendar::query()
                ->where('company_id', $companyId)
                ->with(['beginningBalance'])
                ->orderByDesc('fiscal_year')
                ->get();
        }

        return view('admin.finance.beginning-balance.index', compact(
            'companies',
            'companyId',
            'calendars'
        ));
    }

    public function editView(string $fiscalCalendarId)
    {
        $calendar = FiscalCalendar::with('company:id,name,code')->findOrFail($fiscalCalendarId);
        $this->assertCompanyAccess($calendar->company_id);

        $balance = $this->service->ensureDraftForCalendar($calendar, auth('web')->id(), true);
        if ($balance->isPosted() && ! $balance->journal_id) {
            $balance = $this->service->ensurePostedJournal($balance, auth('web')->id());
        }
        $balance->load(['lines.account', 'fiscalCalendar', 'company', 'journal']);

        $canEdit = $balance->isDraft() && ! $this->service->hasJournalMutations($balance);

        return view('admin.finance.beginning-balance.edit', [
            'balance' => $balance,
            'calendar' => $calendar,
            'canEdit' => $canEdit,
            'hasJournals' => $this->service->hasJournalMutations($balance),
        ]);
    }

    public function saveData(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|uuid',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'nullable|array',
            'lines.*.account_id' => 'required|uuid',
            'lines.*.debit' => 'nullable',
            'lines.*.credit' => 'nullable',
        ]);

        $balance = BeginningBalance::with('lines.account')->findOrFail($validated['id']);
        $this->assertCompanyAccess($balance->company_id);

        $this->service->saveDraft(
            $balance,
            $validated['lines'] ?? [],
            $validated['notes'] ?? null,
            auth('web')->id()
        );

        return redirect()
            ->route('finance.beginning-balance.edit.view', $balance->fiscal_calendar_id)
            ->with('success', 'Beginning balance draft saved.');
    }

    public function suggestData(Request $request)
    {
        $validated = $request->validate(['id' => 'required|uuid']);

        $balance = BeginningBalance::with(['lines.account', 'fiscalCalendar'])->findOrFail($validated['id']);
        $this->assertCompanyAccess($balance->company_id);

        $this->service->applySuggestionFromPrevious($balance, auth('web')->id());

        return redirect()
            ->route('finance.beginning-balance.edit.view', $balance->fiscal_calendar_id)
            ->with('success', 'Amounts suggested from previous fiscal year. Review and save/book.');
    }

    public function bookData(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|uuid',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'nullable|array',
            'lines.*.account_id' => 'required|uuid',
            'lines.*.debit' => 'nullable',
            'lines.*.credit' => 'nullable',
        ]);

        $balance = BeginningBalance::with('lines.account')->findOrFail($validated['id']);
        $this->assertCompanyAccess($balance->company_id);

        if (! empty($validated['lines'])) {
            $this->service->saveDraft(
                $balance,
                $validated['lines'],
                $validated['notes'] ?? null,
                auth('web')->id()
            );
            $balance->refresh()->load('lines.account');
        }

        $this->service->book($balance, auth('web')->id());

        return redirect()
            ->route('finance.beginning-balance.edit.view', $balance->fiscal_calendar_id)
            ->with('success', 'Beginning balance booked successfully.');
    }

    public function unpostData(Request $request)
    {
        $validated = $request->validate(['id' => 'required|uuid']);

        $balance = BeginningBalance::findOrFail($validated['id']);
        $this->assertCompanyAccess($balance->company_id);

        $this->service->unpost($balance, auth('web')->id());

        return redirect()
            ->route('finance.beginning-balance.edit.view', $balance->fiscal_calendar_id)
            ->with('success', 'Beginning balance unposted. You can edit again.');
    }
}
