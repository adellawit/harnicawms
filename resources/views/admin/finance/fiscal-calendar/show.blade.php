<x-app-layout>
    @section('title', 'Fiscal Calendar Detail | ')

    @push('vendor-css')
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report" style="padding-bottom: 70px !important;">
        @php
            $hasUpdatePermission = session('permissions.Fiscal Calendar.is_update', false) == 1;
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Fiscal Calendar', 'url' => route('finance.fiscal-calendar.index.view', ['company_id' => $calendar->company_id])],
            ['label' => (string) $calendar->fiscal_year, 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card fin-toolbar mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="fin-kpi-icon bg-label-warning text-warning"><i class="ti ti-calendar-stats"></i></div>
                        <div>
                            <div class="text-muted small mb-0">{{ $calendar->company?->name }}</div>
                            <div class="fin-company">{{ $calendar->name }}</div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                <span class="small">
                                    {{ format_date_id($calendar->start_date) }}
                                    <span class="text-muted mx-1">–</span>
                                    {{ format_date_id($calendar->end_date) }}
                                </span>
                                @if($calendar->is_closed)
                                    <span class="badge bg-label-secondary">Calendar Closed</span>
                                @else
                                    <span class="badge bg-label-success">Calendar Open</span>
                                @endif
                                @if(! $calendar->is_active)
                                    <span class="badge bg-label-warning">Inactive</span>
                                @endif
                            </div>
                            @if($calendar->notes)
                                <div class="text-muted small mt-2">{{ $calendar->notes }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        @if($hasUpdatePermission && ! $calendar->trashed())
                            <a href="{{ route('finance.fiscal-calendar.edit.view', $calendar->id) }}"
                                class="btn btn-sm btn-outline-primary d-inline-flex align-items-center justify-content-center"
                                style="width: 6.75rem; height: 2.25rem;">
                                <i class="ti ti-pencil me-1"></i> Edit
                            </a>
                            @if($calendar->is_closed)
                                <form method="POST" action="{{ route('finance.fiscal-calendar.reopen') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="fiscal_calendar_id" value="{{ $calendar->id }}">
                                    <button type="submit"
                                        class="btn btn-sm btn-outline-success d-inline-flex align-items-center justify-content-center"
                                        style="width: 6.75rem; height: 2.25rem;"
                                        onclick="return confirm('Reopen this fiscal calendar?')">
                                        <i class="ti ti-lock-open me-1"></i> Reopen
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('finance.fiscal-calendar.close') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="fiscal_calendar_id" value="{{ $calendar->id }}">
                                    <button type="submit"
                                        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center"
                                        style="width: 6.75rem; height: 2.25rem;"
                                        onclick="return confirm('Close this fiscal calendar? All periods must be closed/locked.')">
                                        <i class="ti ti-lock me-1"></i> Close
                                    </button>
                                </form>
                            @endif
                        @endif
                        <a href="{{ route('finance.fiscal-calendar.index.view', ['company_id' => $calendar->company_id]) }}"
                            class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center"
                            style="width: 6.75rem; height: 2.25rem;">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card fin-section accent-warning">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Periods</h5>
                    <div class="fin-section-sub">{{ $calendar->periods->count() }} accounting periods</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fin-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Status</th>
                            @if($hasUpdatePermission && ! $calendar->is_closed)
                                <th class="text-end pe-3">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($calendar->periods as $period)
                            @php
                                $badge = match ($period->status) {
                                    'open' => 'bg-label-success',
                                    'closed' => 'bg-label-secondary',
                                    'locked' => 'bg-label-danger',
                                    default => 'bg-label-secondary',
                                };
                            @endphp
                            <tr>
                                <td>{{ $period->period_no }}</td>
                                <td><code>{{ $period->code }}</code></td>
                                <td>
                                    {{ $period->name }}
                                    @if($period->is_adjustment)
                                        <span class="badge bg-label-info ms-1">Adj</span>
                                    @endif
                                </td>
                                <td>{{ format_date_id($period->start_date) }}</td>
                                <td>{{ format_date_id($period->end_date) }}</td>
                                <td><span class="badge {{ $badge }}">{{ $period->statusLabel() }}</span></td>
                                @if($hasUpdatePermission && ! $calendar->is_closed)
                                    <td class="text-end pe-3">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                <i class="ti ti-dots-vertical text-primary"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                @if($period->status !== 'open')
                                                    <li>
                                                        <form method="POST" action="{{ route('finance.fiscal-calendar.period.status') }}">
                                                            @csrf
                                                            <input type="hidden" name="period_id" value="{{ $period->id }}">
                                                            <input type="hidden" name="status" value="open">
                                                            <button type="submit" class="dropdown-item"
                                                                @disabled($period->status === 'locked')
                                                                onclick="return confirm('Set period to Open?')">
                                                                <i class="ti ti-lock-open me-2 text-success"></i>Set Open
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                                @if($period->status !== 'closed')
                                                    <li>
                                                        <form method="POST" action="{{ route('finance.fiscal-calendar.period.status') }}">
                                                            @csrf
                                                            <input type="hidden" name="period_id" value="{{ $period->id }}">
                                                            <input type="hidden" name="status" value="closed">
                                                            <button type="submit" class="dropdown-item"
                                                                onclick="return confirm('Close this period?')">
                                                                <i class="ti ti-circle-x me-2 text-secondary"></i>Close
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                                @if($period->status !== 'locked')
                                                    <li>
                                                        <form method="POST" action="{{ route('finance.fiscal-calendar.period.status') }}">
                                                            @csrf
                                                            <input type="hidden" name="period_id" value="{{ $period->id }}">
                                                            <input type="hidden" name="status" value="locked">
                                                            <button type="submit" class="dropdown-item"
                                                                onclick="return confirm('Lock this period?')">
                                                                <i class="ti ti-lock me-2 text-danger"></i>Lock
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $hasUpdatePermission && ! $calendar->is_closed ? 7 : 6 }}" class="text-center text-muted py-4">
                                    No periods found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
