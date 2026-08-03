<x-app-layout>
    @section('title', 'Cash & Bank Mutations | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        @include('admin.finance.cash-bank._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report">
        @php
            $totalDebit = collect($mutations)->sum('debit');
            $totalCredit = collect($mutations)->sum('credit');
            $reconciledCount = collect($mutations)->where('is_reconciled', true)->count();
            $openCount = count($mutations) - $reconciledCount;
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Cash & Bank', 'url' => route('finance.cash-bank.index.view', ['company_id' => $companyId])],
            ['label' => $account->code, 'active' => true],
        ]" />

        <div class="card fin-toolbar mb-3">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-info text-info">
                        <i class="ti ti-list-details"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-0">Account mutations</div>
                        <div class="fin-company">
                            <span class="fin-account-code">{{ $account->code }}</span>{{ $account->name }}
                        </div>
                        <div class="small text-muted">{{ $dateFrom }} — {{ $dateTo }}</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('finance.cash-bank.reconciliation.history', ['accountId' => $account->id, 'company_id' => $companyId]) }}"
                        class="btn btn-label-secondary btn-sm">
                        <i class="ti ti-history me-1"></i> History
                    </a>
                    <a href="{{ route('finance.cash-bank.index.view', ['company_id' => $companyId]) }}"
                        class="btn btn-label-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3 fin-kpi">
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="fin-kpi-label">Book balance</div>
                        <div class="fin-kpi-value text-primary">{{ format_number((float) $balance, 2, true) }}</div>
                        <div class="small text-muted mt-1">As of {{ $dateTo }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="fin-kpi-label">Period debit</div>
                        <div class="fin-kpi-value text-success">{{ format_number($totalDebit, 2, true) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="fin-kpi-label">Period credit</div>
                        <div class="fin-kpi-value text-danger">{{ format_number($totalCredit, 2, true) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="fin-kpi-label">Lines</div>
                        <div class="fin-kpi-value">{{ count($mutations) }}</div>
                        <div class="small text-muted mt-1">{{ $reconciledCount }} reconciled · {{ $openCount }} open</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card fin-section accent-info">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Mutations</h5>
                    <div class="fin-section-sub">Posted journal lines with running balance</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fin-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 10%">Date</th>
                            <th style="width: 16%">Journal No / ID</th>
                            <th>Description</th>
                            <th class="text-end" style="width: 12%">Debit</th>
                            <th class="text-end" style="width: 12%">Credit</th>
                            <th class="text-end" style="width: 13%">Balance</th>
                            <th style="width: 10%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mutations as $row)
                            <tr class="{{ !empty($row['is_reconciled']) ? 'cb-line-cleared' : '' }}">
                                <td>{{ $row['journal_date'] }}</td>
                                <td>
                                    @if(!empty($row['journal_id']))
                                        <a href="{{ route('finance.jurnal-umum.show.view', $row['journal_id']) }}" class="text-decoration-none">
                                            <div class="fw-semibold text-primary">{{ $row['journal_no'] ?: '—' }}</div>
                                            <div class="small text-muted font-monospace" title="{{ $row['journal_id'] }}">{{ $row['journal_id'] }}</div>
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $row['description'] ?: '—' }}</td>
                                <td class="text-end">{{ $row['debit'] > 0 ? format_number($row['debit'], 2, true) : '—' }}</td>
                                <td class="text-end">{{ $row['credit'] > 0 ? format_number($row['credit'], 2, true) : '—' }}</td>
                                <td class="text-end fin-amount">{{ format_number($row['running_balance'], 2, true) }}</td>
                                <td>
                                    @if($row['is_reconciled'])
                                        <span class="badge bg-label-success">Reconciled</span>
                                    @else
                                        <span class="badge bg-label-secondary">Open</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">No mutations in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($mutations) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="3">Period totals</td>
                                <td class="text-end">{{ format_number($totalDebit, 2, true) }}</td>
                                <td class="text-end">{{ format_number($totalCredit, 2, true) }}</td>
                                <td class="text-end">{{ format_number((float) $balance, 2, true) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter Period">
        <form method="GET" action="{{ route('finance.cash-bank.mutations.view', $account->id) }}" id="filterForm">
            <input type="hidden" name="company_id" value="{{ $companyId }}">
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label">From</label>
                    <input type="text" name="date_from" class="form-control flatpickr-date" value="{{ $dateFrom }}" required>
                </div>
                <div class="col-6">
                    <label class="form-label">To</label>
                    <input type="text" name="date_to" class="form-control flatpickr-date" value="{{ $dateTo }}" required>
                </div>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="filterForm" class="btn btn-primary">Apply</button>
        </x-slot:footer>
    </x-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', disableMobile: true, allowInput: true });
            });
        </script>
    @endpush
</x-app-layout>
