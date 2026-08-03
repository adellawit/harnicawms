<x-app-layout>
    @section('title', 'Cash Flow | ')
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
            ['label' => 'Cash Flow', 'active' => true],
        ]" />

        <div class="card fin-toolbar mb-3 d-print-none">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-info text-info">
                        <i class="ti ti-arrows-exchange"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-0">Cash flow — indirect method</div>
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
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Operating</div>
                            <div class="fin-kpi-value {{ $report['operating_total'] < 0 ? 'text-danger' : 'text-success' }}">
                                {{ format_number($report['operating_total'], 2, true) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Investing</div>
                            <div class="fin-kpi-value {{ $report['investing_total'] < 0 ? 'text-danger' : '' }}">
                                {{ format_number($report['investing_total'], 2, true) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Financing</div>
                            <div class="fin-kpi-value {{ $report['financing_total'] < 0 ? 'text-danger' : '' }}">
                                {{ format_number($report['financing_total'], 2, true) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Net change</div>
                            <div class="fin-kpi-value {{ $report['net_change'] < 0 ? 'text-danger' : 'text-primary' }}">
                                {{ format_number($report['net_change'], 2, true) }}
                            </div>
                            @if($report['is_reconciled'])
                                <div class="fin-status-pill bg-label-success text-success mt-2">
                                    <i class="ti ti-circle-check"></i> Cash reconciled
                                </div>
                            @else
                                <div class="fin-status-pill bg-label-warning text-warning mt-2">
                                    <i class="ti ti-alert-triangle"></i> Diff {{ format_number($report['difference'], 2, true) }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @php
                $operatingRows = array_merge(
                    [['label' => 'Net Income', 'amount' => $report['net_income'], 'strong' => true]],
                    collect($report['operating'])->map(fn ($r) => [
                        'account' => $r['account'],
                        'amount' => $r['amount'],
                        'indent' => true,
                    ])->all()
                );
            @endphp

            <div class="row g-3">
                <div class="col-xl-7">
                    <x-finance.report-section
                        title="Operating activities"
                        accent="success"
                        subtitle="Net income + working capital adjustments"
                        column-label="Description"
                        total-label="Net cash from operating"
                        :total-amount="$report['operating_total']"
                    >
                        @include('admin.finance.reports._amount-rows', [
                            'rows' => $operatingRows,
                            'emptyMessage' => 'No operating adjustments.',
                        ])
                    </x-finance.report-section>

                    <x-finance.report-section
                        title="Investing activities"
                        accent="info"
                        column-label="Description"
                        total-label="Net cash from investing"
                        :total-amount="$report['investing_total']"
                    >
                        @include('admin.finance.reports._amount-rows', [
                            'rows' => collect($report['investing'])->map(fn ($r) => [
                                'account' => $r['account'],
                                'amount' => $r['amount'],
                            ])->all(),
                            'emptyMessage' => 'No investing CF accounts mapped.',
                        ])
                    </x-finance.report-section>

                    <x-finance.report-section
                        title="Financing activities"
                        accent="warning"
                        column-label="Description"
                        total-label="Net cash from financing"
                        :total-amount="$report['financing_total']"
                    >
                        @include('admin.finance.reports._amount-rows', [
                            'rows' => collect($report['financing'])->map(fn ($r) => [
                                'account' => $r['account'],
                                'amount' => $r['amount'],
                            ])->all(),
                            'emptyMessage' => 'No financing CF accounts mapped.',
                        ])
                    </x-finance.report-section>
                </div>
                <div class="col-xl-5">
                    <div class="card fin-section accent-primary mb-3">
                        <div class="fin-section-head">
                            <div>
                                <h5 class="fin-section-title">Cash bridge</h5>
                                <div class="fin-section-sub">Beginning → net change → ending</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                <div>
                                    <div class="small text-muted">Cash at beginning</div>
                                    <div class="fin-amount fs-5">{{ format_number($report['cash_beginning'], 2, true) }}</div>
                                </div>
                                <i class="ti ti-wallet text-muted fs-3"></i>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                <div>
                                    <div class="small text-muted">Net increase / (decrease)</div>
                                    <div class="fin-amount fs-5 {{ $report['net_change'] < 0 ? 'text-danger' : 'text-success' }}">
                                        {{ format_number($report['net_change'], 2, true) }}
                                    </div>
                                </div>
                                <i class="ti ti-arrows-up-down {{ $report['net_change'] < 0 ? 'text-danger' : 'text-success' }} fs-3"></i>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                <div>
                                    <div class="small text-muted">Cash at end (book)</div>
                                    <div class="fin-amount fs-4 text-primary">{{ format_number($report['cash_ending'], 2, true) }}</div>
                                </div>
                                <i class="ti ti-circle-check text-primary fs-3"></i>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-muted">Proof: begin + net</div>
                                    <div class="fin-amount {{ $report['is_reconciled'] ? 'text-success' : 'text-warning' }}">
                                        {{ format_number($report['cash_ending_computed'], 2, true) }}
                                    </div>
                                </div>
                                @if($report['is_reconciled'])
                                    <span class="badge bg-label-success">OK</span>
                                @else
                                    <span class="badge bg-label-warning">Check mapping</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="fin-empty text-center text-muted py-5">Select a company to view the Cash Flow statement.</div>
        @endif
    </div>

    <x-modal id="filterModal" title="Filter">
        <form method="GET" action="{{ route('finance.cash-flow.index.view') }}" id="filterForm">
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
            <a href="{{ route('finance.cash-flow.index.view') }}" class="btn btn-label-dark">Reset</a>
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
