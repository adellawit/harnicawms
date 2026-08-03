<x-app-layout>
    @section('title', 'Beginning Balance | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report">
        @php
            $hasUpdatePermission = session('permissions.Beginning Balance.is_update', false) == 1;
            $selectedCompany = $companies->firstWhere('id', $companyId);
            $postedCount = $calendars->filter(fn ($c) => $c->beginningBalance?->isPosted())->count();
            $draftCount = $calendars->filter(fn ($c) => $c->beginningBalance && ! $c->beginningBalance->isPosted())->count();
            $notStarted = $calendars->filter(fn ($c) => ! $c->beginningBalance)->count();
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Beginning Balance', 'active' => true],
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
                    <div class="fin-kpi-icon bg-label-success text-success"><i class="ti ti-scale"></i></div>
                    <div>
                        <div class="text-muted small mb-0">Opening balances per fiscal year</div>
                        @if($selectedCompany)
                            <div class="fin-company">{{ $selectedCompany->name }}</div>
                        @endif
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
            </div>
        </div>

        <div class="row g-3 mb-3 fin-kpi">
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Fiscal years</div><div class="fin-kpi-value">{{ $calendars->count() }}</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Posted</div><div class="fin-kpi-value text-success">{{ $postedCount }}</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Draft</div><div class="fin-kpi-value text-warning">{{ $draftCount }}</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Not started</div><div class="fin-kpi-value text-muted">{{ $notStarted }}</div></div></div>
            </div>
        </div>

        <div class="card fin-section accent-success">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Beginning Balance</h5>
                    <div class="fin-section-sub">Balance-sheet opening per fiscal calendar</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fin-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 12%">Year</th>
                            <th>Fiscal Calendar</th>
                            <th style="width: 16%">Status</th>
                            <th class="text-end pe-3" style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($calendars as $calendar)
                            @php $bb = $calendar->beginningBalance; @endphp
                            <tr>
                                <td><span class="fin-amount fs-6">{{ $calendar->fiscal_year }}</span></td>
                                <td class="fw-medium">{{ $calendar->name }}</td>
                                <td>
                                    @if(! $bb)
                                        <span class="badge bg-label-secondary">Not started</span>
                                    @elseif($bb->isPosted())
                                        <span class="badge bg-label-success">Posted</span>
                                    @else
                                        <span class="badge bg-label-warning">Draft</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('finance.beginning-balance.edit.view', $calendar->id) }}"
                                        class="btn btn-sm {{ $bb?->isPosted() ? 'btn-label-secondary' : 'btn-label-primary' }}">
                                        <i class="ti {{ $bb?->isPosted() ? 'ti-eye' : 'ti-edit' }} me-1"></i>
                                        {{ $bb?->isPosted() ? 'View' : ($hasUpdatePermission ? 'Open / Edit' : 'View') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    No fiscal calendars yet. Create a Fiscal Calendar first.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <form method="GET" action="{{ route('finance.beginning-balance.index.view') }}" id="filterForm">
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
        </form>
        <x-slot:footer>
            <a href="{{ route('finance.beginning-balance.index.view') }}" class="btn btn-label-dark">Reset</a>
            <button type="submit" form="filterForm" class="btn btn-primary">Apply</button>
        </x-slot:footer>
    </x-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
    @endpush
</x-app-layout>
