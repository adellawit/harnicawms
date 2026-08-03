<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Models\Accounting\FiscalCalendar;
use App\Models\Accounting\FiscalPeriod;
use App\Models\BusinessUnit;
use App\Services\Accounting\FiscalCalendarService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FiscalCalendarController extends Controller
{
    public function __construct(
        private readonly FiscalCalendarService $fiscalService
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
        $status = $request->get('status', 'active');
        $search = trim((string) $request->get('q', ''));
        $companies = $this->companiesQuery()->orderBy('name')->get();
        $companyId = $request->get('company_id') ?: $companies->first()?->id;
        $isFilter = $status !== 'active' || $search !== '';

        $calendars = collect();
        if ($companyId) {
            $this->assertCompanyAccess($companyId);

            $query = FiscalCalendar::query()
                ->withCount('periods')
                ->where('company_id', $companyId);

            if ($status === 'active') {
                $query->where('is_active', true)->where('is_closed', false);
            } elseif ($status === 'closed') {
                $query->where('is_closed', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'deleted') {
                $query->onlyTrashed();
            } elseif ($status === 'all') {
                $query->withTrashed();
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('fiscal_year', 'ILIKE', "%{$search}%");
                });
            }

            $calendars = $query->orderByDesc('fiscal_year')->get();
        }

        return view('admin.finance.fiscal-calendar.index', compact(
            'companies',
            'companyId',
            'calendars',
            'status',
            'search',
            'isFilter'
        ));
    }

    public function insertView(Request $request)
    {
        $companies = $this->companiesQuery()->orderBy('name')->get();
        $year = (int) ($request->get('year') ?: now()->year);
        $defaultCompanyId = $request->get('company_id') ?: $companies->first()?->id;

        $openCalendar = null;
        if ($defaultCompanyId) {
            $this->assertCompanyAccess($defaultCompanyId);
            $openCalendar = FiscalCalendar::query()
                ->where('company_id', $defaultCompanyId)
                ->where('is_closed', false)
                ->orderByDesc('fiscal_year')
                ->first();
        }

        return view('admin.finance.fiscal-calendar.insert', [
            'companies' => $companies,
            'defaultCompanyId' => $defaultCompanyId,
            'defaultYear' => $year,
            'defaultStart' => format_date_id(sprintf('%d-01-01', $year)),
            'defaultEnd' => format_date_id(sprintf('%d-12-31', $year)),
            'openCalendar' => $openCalendar,
        ]);
    }

    public function insertData(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|uuid',
            'name' => 'required|string|max:255',
            'fiscal_year' => 'required|integer|min:2000|max:2100',
            'start_date' => 'required|date_format:d/m/Y',
            'end_date' => 'required|date_format:d/m/Y|after_or_equal:start_date',
            'notes' => 'nullable|string|max:1000',
            'include_adjustment_period' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $this->assertCompanyAccess($validated['company_id']);

        $exists = FiscalCalendar::query()
            ->where('company_id', $validated['company_id'])
            ->where('fiscal_year', $validated['fiscal_year'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'fiscal_year' => 'A fiscal calendar for this year already exists.',
            ]);
        }

        $result = $this->fiscalService->createWithPeriods([
            'company_id' => $validated['company_id'],
            'name' => $validated['name'],
            'fiscal_year' => (int) $validated['fiscal_year'],
            'start_date' => HelperController::parseDate($validated['start_date']),
            'end_date' => HelperController::parseDate($validated['end_date']),
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_closed' => false,
        ], auth('web')->id(), $request->boolean('include_adjustment_period'));

        return redirect()
            ->route('finance.fiscal-calendar.show.view', $result['calendar']->id)
            ->with('success', "Fiscal calendar created with {$result['periods']} periods.");
    }

    public function showView(string $id)
    {
        $calendar = FiscalCalendar::with(['periods', 'company:id,name,code'])
            ->withTrashed()
            ->findOrFail($id);

        $this->assertCompanyAccess($calendar->company_id);

        return view('admin.finance.fiscal-calendar.show', [
            'calendar' => $calendar,
            'statuses' => FiscalPeriod::STATUSES,
        ]);
    }

    public function editView(string $id)
    {
        $calendar = FiscalCalendar::with(['company:id,name,code'])->withTrashed()->findOrFail($id);
        $this->assertCompanyAccess($calendar->company_id);

        return view('admin.finance.fiscal-calendar.edit', [
            'calendar' => $calendar,
            'companies' => $this->companiesQuery()->orderBy('name')->get(),
        ]);
    }

    public function editData(Request $request)
    {
        $calendar = FiscalCalendar::withTrashed()->findOrFail($request->input('id'));
        $this->assertCompanyAccess($calendar->company_id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable',
        ]);

        $calendar->update([
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()
            ->route('finance.fiscal-calendar.show.view', $calendar->id)
            ->with('success', 'Fiscal calendar updated.');
    }

    public function deleteData(Request $request)
    {
        $request->validate(['fiscal_calendar_id_deleted' => 'required|uuid']);

        $calendar = FiscalCalendar::findOrFail($request->input('fiscal_calendar_id_deleted'));
        $this->assertCompanyAccess($calendar->company_id);

        if ($calendar->is_closed) {
            throw ValidationException::withMessages([
                'calendar' => 'Closed fiscal calendars cannot be deleted.',
            ]);
        }

        $calendar->deleted_by = auth('web')->id();
        $calendar->save();
        $calendar->delete();

        return redirect()
            ->route('finance.fiscal-calendar.index.view', ['company_id' => $calendar->company_id])
            ->with('success', 'Fiscal calendar deleted.');
    }

    public function restoreData(Request $request)
    {
        $request->validate(['fiscal_calendar_id_restored' => 'required|uuid']);

        $calendar = FiscalCalendar::onlyTrashed()->findOrFail($request->input('fiscal_calendar_id_restored'));
        $this->assertCompanyAccess($calendar->company_id);

        $calendar->restore();
        $calendar->deleted_by = null;
        $calendar->updated_by = auth('web')->id();
        $calendar->save();

        return redirect()
            ->route('finance.fiscal-calendar.index.view', ['company_id' => $calendar->company_id])
            ->with('success', 'Fiscal calendar restored.');
    }

    public function setPeriodStatus(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|uuid',
            'status' => ['required', Rule::in(array_keys(FiscalPeriod::STATUSES))],
        ]);

        $period = FiscalPeriod::with('calendar')->findOrFail($validated['period_id']);
        $this->assertCompanyAccess($period->calendar->company_id);

        $this->fiscalService->setPeriodStatus($period, $validated['status'], auth('web')->id());

        return redirect()
            ->route('finance.fiscal-calendar.show.view', $period->fiscal_calendar_id)
            ->with('success', 'Period status updated to '.FiscalPeriod::STATUSES[$validated['status']].'.');
    }

    public function closeCalendar(Request $request)
    {
        $request->validate(['fiscal_calendar_id' => 'required|uuid']);

        $calendar = FiscalCalendar::findOrFail($request->input('fiscal_calendar_id'));
        $this->assertCompanyAccess($calendar->company_id);

        $this->fiscalService->closeCalendar($calendar, auth('web')->id());

        return redirect()
            ->route('finance.fiscal-calendar.show.view', $calendar->id)
            ->with('success', 'Fiscal calendar closed.');
    }

    public function reopenCalendar(Request $request)
    {
        $request->validate(['fiscal_calendar_id' => 'required|uuid']);

        $calendar = FiscalCalendar::findOrFail($request->input('fiscal_calendar_id'));
        $this->assertCompanyAccess($calendar->company_id);

        $this->fiscalService->reopenCalendar($calendar, auth('web')->id());

        return redirect()
            ->route('finance.fiscal-calendar.show.view', $calendar->id)
            ->with('success', 'Fiscal calendar reopened.');
    }
}
