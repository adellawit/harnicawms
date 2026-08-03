<x-app-layout>
    @section('title', 'General Ledger | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report">
        @php
            $selectedCompany = $companies->firstWhere('id', $companyId);
            $typeBadge = [
                'asset' => 'bg-label-primary',
                'liability' => 'bg-label-warning',
                'equity' => 'bg-label-success',
                'revenue' => 'bg-label-info',
                'expense' => 'bg-label-danger',
            ];
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'General Ledger', 'active' => true],
        ]" />

        <div class="card fin-toolbar mb-3 d-print-none">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-primary text-primary">
                        <i class="ti ti-list-details"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-0">Posted lines by account · running balance</div>
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
            @php
                $activeCount = collect($report['sections'])->filter(fn ($s) => count($s['lines']) > 0)->count();
                $idleCount = $report['account_count'] - $activeCount;
                $periodDebit = collect($report['sections'])->sum('total_debit');
                $periodCredit = collect($report['sections'])->sum('total_credit');
            @endphp

            <div class="row g-3 mb-3 fin-kpi">
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Accounts</div>
                            <div class="fin-kpi-value">{{ $report['account_count'] }}</div>
                            <div class="small text-muted mt-1">{{ $activeCount }} with activity · {{ $idleCount }} idle</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Period debit</div>
                            <div class="fin-kpi-value text-primary">{{ format_number($periodDebit, 2, true) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Period credit</div>
                            <div class="fin-kpi-value text-warning">{{ format_number($periodCredit, 2, true) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-kpi-label">Filter</div>
                            <div class="fw-semibold">
                                {{ $accountType ? ($accountTypes[$accountType] ?? $accountType) : 'All types' }}
                            </div>
                            <div class="small text-muted mt-1">
                                {{ $accountId ? ($accounts->firstWhere('id', $accountId)?->code ?? '1 account') : 'All accounts' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion" id="glAccordion">
                @forelse($report['sections'] as $idx => $section)
                    @php
                        $account = $section['account'];
                        $hasLines = count($section['lines']) > 0;
                        $collapseId = 'gl-acc-'.$idx;
                        $badgeClass = $typeBadge[$account->account_type] ?? 'bg-label-secondary';
                    @endphp
                    <div class="card fin-gl-card mb-2 {{ $hasLines ? 'is-active' : 'is-idle' }}">
                        <div class="card-header fin-gl-head py-3" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" role="button" aria-expanded="{{ $hasLines ? 'true' : 'false' }}">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <i class="ti ti-chevron-down text-muted"></i>
                                    <span class="fin-account-code">{{ $account->code }}</span>
                                    <span class="fw-semibold">{{ $account->name }}</span>
                                    <span class="badge {{ $badgeClass }} text-capitalize">{{ $account->account_type }}</span>
                                    @unless($hasLines)
                                        <span class="badge bg-label-secondary">No activity</span>
                                    @else
                                        <span class="badge bg-label-success">{{ count($section['lines']) }} lines</span>
                                    @endunless
                                </div>
                                <div class="fin-gl-meta">
                                    <span class="fin-gl-chip">Open <strong>{{ format_number($section['opening_balance'], 2, true) }}</strong></span>
                                    <span class="fin-gl-chip">Close <strong>{{ format_number($section['closing_balance'], 2, true) }}</strong></span>
                                </div>
                            </div>
                        </div>
                        <div id="{{ $collapseId }}" class="collapse {{ $hasLines ? 'show' : '' }}" data-bs-parent="">
                            <div class="card-datatable">
                                <table class="table fin-table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 10%">Date</th>
                                            <th style="width: 18%">Journal No / ID</th>
                                            <th>Description</th>
                                            <th class="text-end" style="width: 12%">Debit</th>
                                            <th class="text-end" style="width: 12%">Credit</th>
                                            <th class="text-end" style="width: 14%">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="fin-row-header">
                                            <td colspan="3" class="fst-italic text-muted">Opening balance</td>
                                            <td class="text-end text-muted">—</td>
                                            <td class="text-end text-muted">—</td>
                                            <td class="text-end fin-amount">{{ format_number($section['opening_balance'], 2, true) }}</td>
                                        </tr>
                                        @forelse($section['lines'] as $line)
                                            <tr>
                                                <td>{{ $line['journal_date'] }}</td>
                                                <td>
                                                    @if(!empty($line['journal_id']))
                                                        <a href="{{ route('finance.jurnal-umum.show.view', $line['journal_id']) }}" class="d-print-none text-decoration-none">
                                                            <div class="fw-semibold text-primary">{{ $line['journal_no'] ?: $line['journal_label'] }}</div>
                                                            @if(!empty($line['journal_no']))
                                                                <div class="small text-muted font-monospace" title="{{ $line['journal_id'] }}">{{ $line['journal_id'] }}</div>
                                                            @endif
                                                        </a>
                                                        <div class="d-none d-print-block">
                                                            <div class="fw-semibold">{{ $line['journal_no'] ?: $line['journal_label'] }}</div>
                                                            <div class="small">{{ $line['journal_id'] }}</div>
                                                        </div>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>{{ $line['description'] }}</td>
                                                <td class="text-end">{{ $line['debit'] > 0 ? format_number($line['debit'], 2, true) : '—' }}</td>
                                                <td class="text-end">{{ $line['credit'] > 0 ? format_number($line['credit'], 2, true) : '—' }}</td>
                                                <td class="text-end fin-amount">{{ format_number($line['running_balance'], 2, true) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted small py-3">No mutations in period — balances unchanged.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3">Closing / period totals</td>
                                            <td class="text-end">{{ format_number($section['total_debit'], 2, true) }}</td>
                                            <td class="text-end">{{ format_number($section['total_credit'], 2, true) }}</td>
                                            <td class="text-end">{{ format_number($section['closing_balance'], 2, true) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="fin-empty text-center text-muted py-5">No detail accounts found for the selected filters.</div>
                @endforelse
            </div>
        @else
            <div class="fin-empty text-center text-muted py-5">Select a company to view the General Ledger.</div>
        @endif
    </div>

    <x-modal id="filterModal" title="Filter">
        <form method="GET" action="{{ route('finance.general-ledger.index.view') }}" id="filterForm">
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
            <div class="mb-3">
                <label class="form-label">Account Type</label>
                <select name="account_type" class="form-select">
                    <option value="">All types</option>
                    @foreach($accountTypes as $key => $label)
                        <option value="{{ $key }}" @selected($accountType === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Account</label>
                <select name="account_id" class="form-select select2">
                    <option value="">All accounts</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected($accountId === $acc->id)>
                            {{ $acc->code }} — {{ $acc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
        <x-slot:footer>
            <a href="{{ route('finance.general-ledger.index.view') }}" class="btn btn-label-dark">Reset</a>
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
