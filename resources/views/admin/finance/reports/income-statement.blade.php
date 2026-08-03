<x-app-layout>
    @section('title', 'Income Statement | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report">
        @php
            $selectedCompany = $companies->firstWhere('id', $companyId);
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Income Statement', 'active' => true],
        ]" />

        <div class="card fin-toolbar mb-3 d-print-none">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-success text-success">
                        <i class="ti ti-chart-bar"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-0">Profit &amp; loss</div>
                        @if($selectedCompany)
                            <div class="fin-company">{{ $selectedCompany->name }}</div>
                        @endif
                        <div class="small text-muted">{{ $dateFrom }} — {{ $dateTo }}</div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    <button type="button" class="btn btn-label-secondary btn-sm" onclick="window.print()">
                        <i class="ti ti-printer me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>

        @if($report)
            <div class="row g-3 mb-3 fin-kpi">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body d-flex justify-content-between">
                            <div>
                                <div class="fin-kpi-label">Revenue</div>
                                <div class="fin-kpi-value text-success">{{ format_number($report['total_revenue'], 2, true) }}</div>
                            </div>
                            <span class="fin-kpi-icon bg-label-success text-success"><i class="ti ti-trending-up"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body d-flex justify-content-between">
                            <div>
                                <div class="fin-kpi-label">Expense</div>
                                <div class="fin-kpi-value text-danger">{{ format_number($report['total_expense'], 2, true) }}</div>
                            </div>
                            <span class="fin-kpi-icon bg-label-danger text-danger"><i class="ti ti-trending-down"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body d-flex justify-content-between">
                            <div>
                                <div class="fin-kpi-label">Net Income</div>
                                <div class="fin-kpi-value {{ $report['net_income'] < 0 ? 'text-danger' : 'text-primary' }}">
                                    {{ format_number($report['net_income'], 2, true) }}
                                </div>
                            </div>
                            <span class="fin-kpi-icon {{ $report['net_income'] < 0 ? 'bg-label-danger text-danger' : 'bg-label-primary text-primary' }}">
                                <i class="ti ti-report-money"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-6">
                    <x-finance.report-section
                        title="Revenue"
                        accent="success"
                        subtitle="Income for the period"
                        total-label="Total Revenue"
                        :total-amount="$report['total_revenue']"
                    >
                        @include('admin.finance.reports._tree-rows', ['rows' => $report['revenue']])
                    </x-finance.report-section>
                </div>
                <div class="col-xl-6">
                    <x-finance.report-section
                        title="Expense"
                        accent="danger"
                        subtitle="Costs for the period"
                        total-label="Total Expense"
                        :total-amount="$report['total_expense']"
                    >
                        @include('admin.finance.reports._tree-rows', ['rows' => $report['expense']])
                    </x-finance.report-section>

                    <div class="card fin-section accent-primary mb-3">
                        <div class="fin-section-head">
                            <div>
                                <h5 class="fin-section-title">Net Income</h5>
                                <div class="fin-section-sub">Revenue − Expense</div>
                            </div>
                            <div class="text-end">
                                <div class="fin-amount fs-3 {{ $report['net_income'] < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ format_number($report['net_income'], 2, true) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="fin-empty text-center text-muted py-5">Select a company to view the Income Statement.</div>
        @endif
    </div>

    <x-modal id="filterModal" title="Filter">
        <form method="GET" action="{{ route('finance.income-statement.index.view') }}" id="filterForm">
            <div class="mb-3">
                <label class="form-label">Company</label>
                <select name="company_id" class="form-select select2">
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected($companyId == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Date From</label>
                <input type="text" name="date_from" class="form-control flatpickr-date" value="{{ $dateFrom }}" placeholder="DD/MM/YYYY">
            </div>
            <div class="mb-3">
                <label class="form-label">Date To</label>
                <input type="text" name="date_to" class="form-control flatpickr-date" value="{{ $dateTo }}" placeholder="DD/MM/YYYY">
            </div>
        </form>
        <x-slot:footer>
            <a href="{{ route('finance.income-statement.index.view') }}" class="btn btn-label-dark">Reset</a>
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
            document.querySelectorAll('.flatpickr-date').forEach(function (el) {
                flatpickr(el, { dateFormat: 'd/m/Y', allowInput: true });
            });
        </script>
    @endpush
</x-app-layout>
