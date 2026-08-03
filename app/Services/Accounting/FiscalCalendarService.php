<?php

namespace App\Services\Accounting;

use App\Models\Accounting\FiscalCalendar;
use App\Models\Accounting\FiscalPeriod;
use App\Services\Accounting\BeginningBalanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalCalendarService
{
    public function __construct(
        private readonly BeginningBalanceService $beginningBalanceService
    ) {}

    /**
     * Create fiscal calendar and generate monthly periods.
     *
     * @return array{calendar: FiscalCalendar, periods: int}
     */
    public function createWithPeriods(
        array $payload,
        ?string $userId = null,
        bool $includeAdjustmentPeriod = false
    ): array {
        $this->assertNoOpenFiscalCalendar($payload['company_id']);

        return DB::transaction(function () use ($payload, $userId, $includeAdjustmentPeriod) {
            $calendar = FiscalCalendar::create(array_merge($payload, [
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));

            $periods = $this->generateMonthlyPeriods(
                $calendar,
                $includeAdjustmentPeriod,
                $userId
            );

            $this->beginningBalanceService->ensureDraftForCalendar($calendar, $userId, true);

            return [
                'calendar' => $calendar,
                'periods' => count($periods),
            ];
        });
    }

    /**
     * @return list<FiscalPeriod>
     */
    public function generateMonthlyPeriods(
        FiscalCalendar $calendar,
        bool $includeAdjustmentPeriod = false,
        ?string $userId = null
    ): array {
        if ($calendar->periods()->exists()) {
            throw ValidationException::withMessages([
                'periods' => 'Periods already exist for this fiscal calendar.',
            ]);
        }

        $start = Carbon::parse($calendar->start_date)->startOfDay();
        $end = Carbon::parse($calendar->end_date)->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be on or after start date.',
            ]);
        }

        $created = [];
        $cursor = $start->copy();
        $periodNo = 1;

        while ($cursor->lte($end) && $periodNo <= 12) {
            $periodStart = $cursor->copy();
            $periodEnd = $cursor->copy()->endOfMonth()->startOfDay();
            if ($periodEnd->gt($end)) {
                $periodEnd = $end->copy();
            }

            $created[] = FiscalPeriod::create([
                'fiscal_calendar_id' => $calendar->id,
                'period_no' => $periodNo,
                'name' => $periodStart->format('F Y'),
                'code' => sprintf('%04d-%02d', $calendar->fiscal_year, $periodNo),
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'status' => FiscalPeriod::STATUS_OPEN,
                'is_adjustment' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $cursor = $periodEnd->copy()->addDay()->startOfDay();
            $periodNo++;

            if ($cursor->gt($end)) {
                break;
            }
        }

        if ($includeAdjustmentPeriod) {
            $created[] = FiscalPeriod::create([
                'fiscal_calendar_id' => $calendar->id,
                'period_no' => 13,
                'name' => 'Adjustment Period '.$calendar->fiscal_year,
                'code' => sprintf('%04d-ADJ', $calendar->fiscal_year),
                'start_date' => $end->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => FiscalPeriod::STATUS_OPEN,
                'is_adjustment' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        return $created;
    }

    public function setPeriodStatus(FiscalPeriod $period, string $status, ?string $userId = null): FiscalPeriod
    {
        if (! array_key_exists($status, FiscalPeriod::STATUSES)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid period status.',
            ]);
        }

        $calendar = $period->calendar;
        if ($calendar?->is_closed && $status === FiscalPeriod::STATUS_OPEN) {
            throw ValidationException::withMessages([
                'status' => 'Cannot reopen a period when the fiscal calendar is closed.',
            ]);
        }

        // Cannot open a locked period without going closed first? Allow locked -> closed -> open
        if ($period->status === FiscalPeriod::STATUS_LOCKED && $status === FiscalPeriod::STATUS_OPEN) {
            throw ValidationException::withMessages([
                'status' => 'Locked periods must be unlocked to Closed first, then Open.',
            ]);
        }

        $period->update([
            'status' => $status,
            'updated_by' => $userId,
        ]);

        return $period->fresh();
    }

    public function closeCalendar(FiscalCalendar $calendar, ?string $userId = null): FiscalCalendar
    {
        $openCount = $calendar->periods()
            ->where('status', FiscalPeriod::STATUS_OPEN)
            ->count();

        if ($openCount > 0) {
            throw ValidationException::withMessages([
                'calendar' => 'Close or lock all periods before closing the fiscal calendar.',
            ]);
        }

        $calendar->update([
            'is_closed' => true,
            'updated_by' => $userId,
        ]);

        return $calendar->fresh();
    }

    public function reopenCalendar(FiscalCalendar $calendar, ?string $userId = null): FiscalCalendar
    {
        $this->assertNoOpenFiscalCalendar($calendar->company_id, $calendar->id);

        $calendar->update([
            'is_closed' => false,
            'updated_by' => $userId,
        ]);

        return $calendar->fresh();
    }

    /**
     * Only one open (not closed) fiscal calendar is allowed per company.
     */
    protected function assertNoOpenFiscalCalendar(string $companyId, ?string $exceptCalendarId = null): void
    {
        $query = FiscalCalendar::query()
            ->where('company_id', $companyId)
            ->where('is_closed', false);

        if ($exceptCalendarId) {
            $query->where('id', '!=', $exceptCalendarId);
        }

        $open = $query->orderByDesc('fiscal_year')->first();

        if ($open) {
            throw ValidationException::withMessages([
                'calendar' => sprintf(
                    'Cannot open a new fiscal calendar while "%s" (FY %d) is still open. Close it first.',
                    $open->name,
                    $open->fiscal_year
                ),
            ]);
        }
    }
}
