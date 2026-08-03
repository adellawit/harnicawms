<x-app-layout>
    @section('title', 'Balance Sheet | ')
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
            ['label' => 'Balance Sheet', 'active' => true],
        ]" />

        <div class="card fin-toolbar mb-3 d-print-none">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-primary text-primary">
                        <i class="ti ti-scale"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-0">Statement of financial position</div>
                        @if($selectedCompany)
                            <div class="fin-company">{{ $selectedCompany->name }}</div>
                        @endif
                        <div class="small text-muted">As of {{ $asOf }}</div>
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
            @php $totalEquity = $report['total_equity_book'] + $report['current_year_earnings']; @endphp

            <div class="row g-3 mb-3 fin-kpi">
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fin-kpi-label">Total Assets</div>
                                <div class="fin-kpi-value text-primary">{{ format_number($report['total_assets'], 2, true) }}</div>
                            </div>
                            <span class="fin-kpi-icon bg-label-primary text-primary"><i class="ti ti-building-warehouse"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fin-kpi-label">Liabilities</div>
                                <div class="fin-kpi-value">{{ format_number($report['total_liabilities'], 2, true) }}</div>
                            </div>
                            <span class="fin-kpi-icon bg-label-warning text-warning"><i class="ti ti-receipt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fin-kpi-label">Equity + CYE</div>
                                <div class="fin-kpi-value text-success">{{ format_number($totalEquity, 2, true) }}</div>
                            </div>
                            <span class="fin-kpi-icon bg-label-success text-success"><i class="ti ti-chart-pie"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Status</div>
                            @if($report['is_balanced'])
                                <div class="fin-status-pill bg-label-success text-success">
                                    <i class="ti ti-circle-check"></i> Balanced
                                </div>
                            @else
                                <div class="fin-status-pill bg-label-danger text-danger">
                                    <i class="ti ti-alert-triangle"></i> Diff {{ format_number($report['difference'], 2, true) }}
                                </div>
                            @endif
                            <div class="small text-muted mt-2">FY start {{ format_date_id($report['fy_start']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-6">
                    <x-finance.report-section
                        title="Assets"
                        accent="primary"
                        subtitle="Resources owned"
                        total-label="Total Assets"
                        :total-amount="$report['total_assets']"
                    >
                        @include('admin.finance.reports._tree-rows', ['rows' => $report['assets']])
                    </x-finance.report-section>
                </div>
                <div class="col-xl-6">
                    <x-finance.report-section
                        title="Liabilities"
                        accent="warning"
                        subtitle="Obligations owed"
                        total-label="Total Liabilities"
                        :total-amount="$report['total_liabilities']"
                    >
                        @include('admin.finance.reports._tree-rows', ['rows' => $report['liabilities']])
                    </x-finance.report-section>

                    <x-finance.report-section
                        title="Equity"
                        accent="success"
                        subtitle="Owner interest + current year earnings"
                        total-label="Total Equity"
                        :total-amount="$totalEquity"
                        :grand="true"
                    >
                        @include('admin.finance.reports._tree-rows', ['rows' => $report['equity']])
                        <tr class="fin-row-synth">
                            <td>
                                <i class="ti ti-sparkles text-warning me-1"></i>
                                Current Year Earnings
                                <span class="badge bg-label-warning ms-1">Computed</span>
                            </td>
                            <td class="text-end">
                                <span class="fin-amount">{{ format_number($report['current_year_earnings'], 2, true) }}</span>
                            </td>
                        </tr>
                    </x-finance.report-section>

                    <div class="card fin-section accent-secondary mb-3">
                        <div class="fin-section-head">
                            <div>
                                <h5 class="fin-section-title">Liabilities &amp; Equity</h5>
                                <div class="fin-section-sub">Must equal Total Assets</div>
                            </div>
                            <div class="text-end">
                                <div class="fin-amount fs-4 {{ $report['is_balanced'] ? 'text-success' : 'text-danger' }}">
                                    {{ format_number($report['total_liabilities_and_equity'], 2, true) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="fin-empty text-center text-muted py-5">Select a company to view the Balance Sheet.</div>
        @endif
    </div>

    <x-modal id="filterModal" title="Filter">
        <form method="GET" action="{{ route('finance.balance-sheet.index.view') }}" id="filterForm">
            <div class="mb-3">
                <label class="form-label">Company</label>
                <select name="company_id" class="form-select select2">
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected($companyId == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">As of Date</label>
                <input type="text" name="as_of" class="form-control flatpickr-date" value="{{ $asOf }}" placeholder="DD/MM/YYYY">
            </div>
        </form>
        <x-slot:footer>
            <a href="{{ route('finance.balance-sheet.index.view') }}" class="btn btn-label-dark">Reset</a>
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
