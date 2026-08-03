<x-app-layout>
    @section('title', 'Fiscal Calendar | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report">
        @php
            $hasUpdatePermission = session('permissions.Fiscal Calendar.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Fiscal Calendar.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Fiscal Calendar.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
            $selectedCompany = $companies->firstWhere('id', $companyId);
            $openCount = $calendars->filter(fn ($c) => ! $c->trashed() && $c->is_active && ! $c->is_closed)->count();
            $closedCount = $calendars->filter(fn ($c) => ! $c->trashed() && $c->is_closed)->count();
            $periodsTotal = $calendars->sum('periods_count');
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Fiscal Calendar', 'active' => true],
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
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-warning text-warning"><i class="ti ti-calendar-stats"></i></div>
                    <div>
                        <div class="text-muted small mb-0">Fiscal years and accounting periods</div>
                        @if($selectedCompany)
                            <div class="fin-company">{{ $selectedCompany->name }}</div>
                        @endif
                        @if($isFilter)<span class="badge bg-label-warning mt-1">Filter active</span>@endif
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }} btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @if($hasCreatePermission && $companyId)
                        <a href="{{ route('finance.fiscal-calendar.insert.view', ['company_id' => $companyId]) }}" class="btn btn-label-primary btn-sm">
                            <i class="ti ti-plus me-1"></i> Add
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3 fin-kpi">
            <div class="col-sm-6 col-xl-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Calendars</div><div class="fin-kpi-value">{{ $calendars->count() }}</div></div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Open</div><div class="fin-kpi-value text-success">{{ $openCount }}</div></div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Closed</div><div class="fin-kpi-value text-muted">{{ $closedCount }}</div></div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Periods</div><div class="fin-kpi-value text-primary">{{ $periodsTotal }}</div></div></div>
            </div>
        </div>

        <div class="card fin-section accent-warning">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Fiscal Calendars</h5>
                    <div class="fin-section-sub">{{ $calendars->count() }} records</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fin-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 10%">Year</th>
                            <th>Name</th>
                            <th style="width: 22%">Date Range</th>
                            <th class="text-center" style="width: 10%">Periods</th>
                            <th style="width: 12%">Status</th>
                            @if($hasAnyActionPermission)<th class="text-end pe-3" style="width: 100px;">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($calendars as $calendar)
                            <tr class="{{ $calendar->trashed() ? 'table-danger' : '' }}">
                                <td><span class="fin-amount fs-6">{{ $calendar->fiscal_year }}</span></td>
                                <td>
                                    <a href="{{ route('finance.fiscal-calendar.show.view', $calendar->id) }}" class="fw-semibold text-decoration-none">
                                        {{ $calendar->name }}
                                    </a>
                                </td>
                                <td>
                                    {{ format_date_id($calendar->start_date) }}
                                    <span class="text-muted mx-1">–</span>
                                    {{ format_date_id($calendar->end_date) }}
                                </td>
                                <td class="text-center"><span class="badge bg-label-secondary">{{ $calendar->periods_count }}</span></td>
                                <td>
                                    @if($calendar->trashed())
                                        <span class="badge bg-label-danger">Deleted</span>
                                    @elseif($calendar->is_closed)
                                        <span class="badge bg-label-secondary">Closed</span>
                                    @elseif($calendar->is_active)
                                        <span class="badge bg-label-success">Open</span>
                                    @else
                                        <span class="badge bg-label-warning">Inactive</span>
                                    @endif
                                </td>
                                @if($hasAnyActionPermission)
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('finance.fiscal-calendar.show.view', $calendar->id) }}" class="btn btn-sm btn-icon btn-label-info" title="View">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-sm btn-icon btn-label-primary dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                    <i class="ti ti-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('finance.fiscal-calendar.show.view', $calendar->id) }}">
                                                            <i class="ti ti-eye me-2 text-info"></i>View Periods
                                                        </a>
                                                    </li>
                                                    @if($hasUpdatePermission && ! $calendar->trashed())
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('finance.fiscal-calendar.edit.view', $calendar->id) }}">
                                                                <i class="ti ti-pencil me-2 text-warning"></i>Edit
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @if($hasDeletePermission)
                                                        @if($calendar->trashed())
                                                            <li>
                                                                <button type="button" class="dropdown-item"
                                                                    data-bs-toggle="modal" data-bs-target="#restoreModal"
                                                                    data-id="{{ $calendar->id }}" data-name="{{ $calendar->displayLabel() }}">
                                                                    <i class="ti ti-refresh me-2 text-success"></i>Restore
                                                                </button>
                                                            </li>
                                                        @elseif(! $calendar->is_closed)
                                                            <li>
                                                                <button type="button" class="dropdown-item"
                                                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                                    data-id="{{ $calendar->id }}" data-name="{{ $calendar->displayLabel() }}">
                                                                    <i class="ti ti-trash me-2 text-danger"></i>Delete
                                                                </button>
                                                            </li>
                                                        @endif
                                                    @endif
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $hasAnyActionPermission ? 6 : 5 }}" class="text-center text-muted py-5">
                                    No fiscal calendars yet. Click Add to create one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <form method="GET" action="{{ route('finance.fiscal-calendar.index.view') }}" id="filterForm">
            <div class="mb-3">
                <label class="form-label" for="filter_company_id">Company</label>
                <select name="company_id" id="filter_company_id" class="form-select select2">
                    @forelse($companies as $c)
                        <option value="{{ $c->id }}" @selected($companyId == $c->id)>{{ $c->name }}</option>
                    @empty
                        <option value="">No company</option>
                    @endforelse
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="filter_q">Search</label>
                <input type="text" name="q" id="filter_q" class="form-control" value="{{ $search }}" placeholder="Name / year">
            </div>
            <div class="mb-3">
                <label class="form-label" for="filter_status">Status</label>
                <select name="status" id="filter_status" class="form-select select2">
                    <option value="active" @selected($status === 'active')>Open / Active</option>
                    <option value="closed" @selected($status === 'closed')>Closed</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    <option value="deleted" @selected($status === 'deleted')>Deleted</option>
                    <option value="all" @selected($status === 'all')>All</option>
                </select>
            </div>
        </form>
        <x-slot:footer>
            <a href="{{ route('finance.fiscal-calendar.index.view') }}" class="btn btn-label-dark">Reset</a>
            <button type="submit" form="filterForm" class="btn btn-primary">Apply</button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('finance.fiscal-calendar.delete.data')" confirmText="Submit">
        <p>Delete fiscal calendar <strong id="fc-name-deleted"></strong>?</p>
        <input type="hidden" id="fiscal-calendar-id-deleted" name="fiscal_calendar_id_deleted" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('finance.fiscal-calendar.restore.data')" confirmText="Submit">
        <p>Restore fiscal calendar <strong id="fc-name-restore"></strong>?</p>
        <input type="hidden" id="fiscal-calendar-id-restore" name="fiscal_calendar_id_restored" />
    </x-confirm-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('#deleteModal').on('show.bs.modal', function(e) {
                    var btn = $(e.relatedTarget);
                    $('#fiscal-calendar-id-deleted').val(btn.data('id'));
                    $('#fc-name-deleted').text(btn.data('name'));
                });
                $('#restoreModal').on('show.bs.modal', function(e) {
                    var btn = $(e.relatedTarget);
                    $('#fiscal-calendar-id-restore').val(btn.data('id'));
                    $('#fc-name-restore').text(btn.data('name'));
                });
            });
        </script>
    @endpush
</x-app-layout>
