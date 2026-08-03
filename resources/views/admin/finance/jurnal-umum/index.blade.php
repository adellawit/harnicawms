<x-app-layout>
    @section('title', 'Jurnal Umum | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report">
        @php
            $hasCreateEntry = session('permissions.Journal Entry.is_create', false) == 1;
            $hasUpdateEntry = session('permissions.Journal Entry.is_update', false) == 1;
            $selectedCompany = $companies->firstWhere('id', $companyId);
            $totalRecords = $journals instanceof \Illuminate\Pagination\AbstractPaginator ? $journals->total() : (is_countable($journals) ? count($journals) : 0);
            $pageItems = $journals instanceof \Illuminate\Pagination\AbstractPaginator
                ? collect($journals->items())
                : collect($journals);
            $postedOnPage = $pageItems->filter(fn ($j) => method_exists($j, 'isPosted') && $j->isPosted())->count();
            $draftOnPage = $pageItems->count() - $postedOnPage;
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Jurnal Umum', 'active' => true],
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
                    <div class="fin-kpi-icon bg-label-primary text-primary"><i class="ti ti-book"></i></div>
                    <div>
                        <div class="text-muted small mb-0">General journal register</div>
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
                    @if($hasCreateEntry && $companyId)
                        <a href="{{ route('finance.journal-entry.insert.view', ['company_id' => $companyId]) }}" class="btn btn-label-primary btn-sm">
                            <i class="ti ti-plus me-1"></i> New Entry
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3 fin-kpi">
            <div class="col-md-4">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Total journals</div><div class="fin-kpi-value">{{ $totalRecords }}</div></div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Posted (page)</div><div class="fin-kpi-value text-success">{{ $postedOnPage }}</div></div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Draft (page)</div><div class="fin-kpi-value text-warning">{{ $draftOnPage }}</div></div></div>
            </div>
        </div>

        <div class="card fin-section accent-primary">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Jurnal Umum</h5>
                    <div class="fin-section-sub">{{ $totalRecords }} records · posted &amp; draft</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fin-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 10%">Date</th>
                            <th style="width: 16%">Journal No</th>
                            <th>Description</th>
                            <th style="width: 10%">Period</th>
                            <th style="width: 16%">Status</th>
                            <th class="text-center" style="width: 8%">Lines</th>
                            <th class="text-end pe-3" style="width: 90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($journals as $journal)
                            <tr>
                                <td>{{ format_date_id($journal->journal_date) }}</td>
                                <td>
                                    <a href="{{ route('finance.jurnal-umum.show.view', $journal->id) }}" class="text-decoration-none">
                                        <span class="fin-account-code">{{ $journal->journal_no }}</span>
                                    </a>
                                    <div class="small text-muted font-monospace text-truncate" style="max-width: 11rem;" title="{{ $journal->id }}">{{ $journal->id }}</div>
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($journal->description, 60) ?: '—' }}</td>
                                <td><span class="badge bg-label-secondary">{{ $journal->fiscalPeriod?->code ?? '—' }}</span></td>
                                <td>
                                    @if($journal->isPosted())
                                        <span class="badge bg-label-success">Posted</span>
                                    @else
                                        <span class="badge bg-label-warning">Draft</span>
                                    @endif
                                    @if(($journal->journal_type ?? 'manual') === 'opening_balance')
                                        <span class="badge bg-label-info">Opening Balance</span>
                                    @endif
                                    @if($journal->attachments_count)
                                        <i class="ti ti-paperclip text-muted ms-1" title="Has attachment"></i>
                                    @endif
                                </td>
                                <td class="text-center">{{ $journal->lines_count }}</td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('finance.jurnal-umum.show.view', $journal->id) }}" class="btn btn-sm btn-icon btn-label-info" title="View">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon btn-label-primary dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                <i class="ti ti-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('finance.jurnal-umum.show.view', $journal->id) }}">
                                                        <i class="ti ti-eye me-2 text-info"></i>View
                                                    </a>
                                                </li>
                                                @if($hasUpdateEntry || $hasCreateEntry)
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('finance.journal-entry.edit.view', $journal->id) }}">
                                                            <i class="ti ti-pencil me-2 text-warning"></i>{{ $journal->isDraft() ? 'Edit' : 'Open' }}
                                                        </a>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">No journals found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($journals instanceof \Illuminate\Pagination\AbstractPaginator && $journals->hasPages())
                <div class="card-footer">{{ $journals->links() }}</div>
            @endif
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <form method="GET" action="{{ route('finance.jurnal-umum.index.view') }}" id="filterForm">
            <div class="mb-3">
                <label class="form-label" for="filter_company_id">Company</label>
                <select name="company_id" id="filter_company_id" class="form-select select2">
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected($companyId == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="filter_q">Search</label>
                <input type="text" name="q" id="filter_q" class="form-control" value="{{ $search }}" placeholder="Journal no / description">
            </div>
            <div class="mb-3">
                <label class="form-label" for="filter_status">Status</label>
                <select name="status" id="filter_status" class="form-select select2">
                    <option value="all" @selected($status === 'all')>All</option>
                    <option value="draft" @selected($status === 'draft')>Draft</option>
                    <option value="posted" @selected($status === 'posted')>Posted</option>
                </select>
            </div>
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label">Date From</label>
                    <input type="text" name="date_from" class="form-control flatpickr-date" value="{{ $dateFrom }}" placeholder="DD/MM/YYYY">
                </div>
                <div class="col-6">
                    <label class="form-label">Date To</label>
                    <input type="text" name="date_to" class="form-control flatpickr-date" value="{{ $dateTo }}" placeholder="DD/MM/YYYY">
                </div>
            </div>
        </form>
        <x-slot:footer>
            <a href="{{ route('finance.jurnal-umum.index.view') }}" class="btn btn-label-dark">Reset</a>
            <button type="submit" form="filterForm" class="btn btn-primary">Apply</button>
        </x-slot:footer>
    </x-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', disableMobile: true, allowInput: true });
            });
        </script>
    @endpush
</x-app-layout>
