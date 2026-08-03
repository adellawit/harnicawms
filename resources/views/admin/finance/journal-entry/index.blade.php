<x-app-layout>
    @section('title', 'Journal Entry | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasCreateEntry = session('permissions.Journal Entry.is_create', false) == 1;
            $hasUpdateEntry = session('permissions.Journal Entry.is_update', false) == 1;
            $hasDeleteEntry = session('permissions.Journal Entry.is_delete', false) == 1;
            $selectedCompany = $companies->firstWhere('id', $companyId);
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Journal Entry', 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div>
                    <div class="text-muted small mb-0">Create and manage journal entries.</div>
                    @if($selectedCompany)
                        <div class="fw-medium mt-1">{{ $selectedCompany->name }}</div>
                    @endif
                    @if($isFilter)<span class="badge bg-label-warning mt-1">Filter active</span>@endif
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn {{ $isFilter ? 'btn-warning' : 'btn-outline-primary' }} btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @if($hasCreateEntry && $companyId)
                        <a href="{{ route('finance.journal-entry.insert.view', ['company_id' => $companyId]) }}" class="btn btn-primary btn-sm">
                            <i class="ti ti-plus me-1"></i> New Entry
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Journal Entry</h4>
                <span class="text-muted small">
                    @if($journals instanceof \Illuminate\Pagination\AbstractPaginator)
                        {{ $journals->total() }} records
                    @else
                        0 records
                    @endif
                </span>
            </div>
            <div class="card-datatable">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Journal No</th>
                            <th>Description</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th class="text-center">Lines</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($journals as $journal)
                            <tr>
                                <td>{{ format_date_id($journal->journal_date) }}</td>
                                <td><code>{{ $journal->journal_no }}</code></td>
                                <td>{{ \Illuminate\Support\Str::limit($journal->description, 60) ?: '—' }}</td>
                                <td>{{ $journal->fiscalPeriod?->code ?? '—' }}</td>
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
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                            <i class="ti ti-dots-vertical text-primary"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('finance.journal-entry.edit.view', $journal->id) }}">
                                                    <i class="ti ti-{{ $journal->isDraft() ? 'pencil' : 'eye' }} me-2 text-{{ $journal->isDraft() ? 'warning' : 'info' }}"></i>
                                                    {{ $journal->isDraft() ? 'Edit' : 'View' }}
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="{{ route('finance.jurnal-umum.show.view', $journal->id) }}">
                                                    <i class="ti ti-book me-2 text-primary"></i>Open in Jurnal Umum
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    No journal entries yet.
                                    @if($hasCreateEntry && $companyId)
                                        <a href="{{ route('finance.journal-entry.insert.view', ['company_id' => $companyId]) }}">Create one</a>
                                    @endif
                                </td>
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
        <form method="GET" action="{{ route('finance.journal-entry.index.view') }}" id="filterForm">
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
            <a href="{{ route('finance.journal-entry.index.view') }}" class="btn btn-label-dark">Reset</a>
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
