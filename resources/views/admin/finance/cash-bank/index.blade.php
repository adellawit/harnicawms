<x-app-layout>
    @section('title', 'Cash & Bank | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        @include('admin.finance.cash-bank._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report">
        @php
            $hasUpdate = session('permissions.Cash & Bank.is_update', false) == 1;
            $selectedCompany = $companies->firstWhere('id', $companyId);
            $totalBalance = (float) $accounts->sum('book_balance');
            $positiveCount = $accounts->filter(fn ($a) => (float) $a->book_balance > 0.005)->count();
            $zeroCount = $accounts->filter(fn ($a) => abs((float) $a->book_balance) < 0.005)->count();
            $maxAbs = max(0.01, (float) $accounts->max(fn ($a) => abs((float) $a->book_balance)));
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Cash & Bank', 'active' => true],
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
                    <div class="fin-kpi-icon bg-label-primary text-primary">
                        <i class="ti ti-building-bank"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-0">Cash &amp; bank monitor</div>
                        @if($selectedCompany)
                            <div class="fin-company">{{ $selectedCompany->name }}</div>
                        @endif
                        <div class="small text-muted">As of {{ $asOf }}</div>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
            </div>
        </div>

        <div class="row g-3 mb-3 fin-kpi">
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body d-flex justify-content-between">
                        <div>
                            <div class="fin-kpi-label">Total cash</div>
                            <div class="fin-kpi-value text-primary">{{ format_number($totalBalance, 2, true) }}</div>
                        </div>
                        <span class="fin-kpi-icon bg-label-primary text-primary"><i class="ti ti-cash"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body d-flex justify-content-between">
                        <div>
                            <div class="fin-kpi-label">Accounts</div>
                            <div class="fin-kpi-value">{{ $accounts->count() }}</div>
                        </div>
                        <span class="fin-kpi-icon bg-label-info text-info"><i class="ti ti-wallet"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body d-flex justify-content-between">
                        <div>
                            <div class="fin-kpi-label">With balance</div>
                            <div class="fin-kpi-value text-success">{{ $positiveCount }}</div>
                        </div>
                        <span class="fin-kpi-icon bg-label-success text-success"><i class="ti ti-circle-check"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body d-flex justify-content-between">
                        <div>
                            <div class="fin-kpi-label">Zero / empty</div>
                            <div class="fin-kpi-value text-muted">{{ $zeroCount }}</div>
                        </div>
                        <span class="fin-kpi-icon bg-label-secondary text-secondary"><i class="ti ti-circle-dashed"></i></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card fin-section accent-primary">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Cash &amp; Bank Accounts</h5>
                    <div class="fin-section-sub">Book balance as of {{ $asOf }} · {{ $accounts->count() }} accounts</div>
                </div>
                <div class="text-end d-none d-md-block">
                    <div class="fin-section-sub">Total</div>
                    <div class="fin-amount fs-5 text-primary">{{ format_number($totalBalance, 2, true) }}</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fin-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 12%">Code</th>
                            <th>Name</th>
                            <th style="width: 12%">Type</th>
                            <th class="text-end" style="width: 22%">Book Balance</th>
                            <th class="text-end pe-3" style="width: 18%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $account)
                            @php
                                $bal = (float) $account->book_balance;
                                $share = min(100, round(abs($bal) / $maxAbs * 100));
                                $isNeg = $bal < -0.005;
                                $isZero = abs($bal) < 0.005;
                            @endphp
                            <tr class="{{ $isZero ? 'fin-row-zero' : '' }}">
                                <td><span class="fin-account-code">{{ $account->code }}</span></td>
                                <td class="fw-medium">{{ $account->name }}</td>
                                <td><span class="badge bg-label-secondary text-capitalize">{{ $account->account_type }}</span></td>
                                <td class="text-end">
                                    <div class="fin-amount-wrap ms-auto">
                                        <span class="fin-amount {{ $isNeg ? 'text-danger' : ($isZero ? 'text-muted fw-normal' : 'text-primary') }}">
                                            {{ format_number($bal, 2, true) }}
                                        </span>
                                        <div class="fin-bar {{ $isNeg ? 'is-neg' : '' }}" title="{{ $share }}%">
                                            <span style="width: {{ $share }}%"></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end cb-actions">
                                        <a href="{{ route('finance.cash-bank.mutations.view', ['accountId' => $account->id, 'company_id' => $companyId]) }}"
                                            class="btn btn-sm btn-icon btn-label-info" title="Mutations">
                                            <i class="ti ti-list-details"></i>
                                        </a>
                                        @if($hasUpdate)
                                            <button type="button" class="btn btn-sm btn-icon btn-label-success" title="Reconciliation"
                                                data-bs-toggle="modal" data-bs-target="#reconModal"
                                                data-account-id="{{ $account->id }}"
                                                data-account-name="{{ $account->code }} — {{ $account->name }}">
                                                <i class="ti ti-checklist"></i>
                                            </button>
                                        @endif
                                        <a href="{{ route('finance.cash-bank.reconciliation.history', ['accountId' => $account->id, 'company_id' => $companyId]) }}"
                                            class="btn btn-sm btn-icon btn-label-secondary" title="History">
                                            <i class="ti ti-history"></i>
                                        </a>
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon btn-label-primary dropdown-toggle hide-arrow" data-bs-toggle="dropdown" title="More">
                                                <i class="ti ti-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('finance.cash-bank.mutations.view', ['accountId' => $account->id, 'company_id' => $companyId]) }}">
                                                        <i class="ti ti-list-details me-2 text-info"></i>View Mutations
                                                    </a>
                                                </li>
                                                @if($hasUpdate)
                                                    <li>
                                                        <button type="button" class="dropdown-item"
                                                            data-bs-toggle="modal" data-bs-target="#reconModal"
                                                            data-account-id="{{ $account->id }}"
                                                            data-account-name="{{ $account->code }} — {{ $account->name }}">
                                                            <i class="ti ti-checklist me-2 text-success"></i>Reconciliation
                                                        </button>
                                                    </li>
                                                @endif
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('finance.cash-bank.reconciliation.history', ['accountId' => $account->id, 'company_id' => $companyId]) }}">
                                                        <i class="ti ti-history me-2 text-secondary"></i>Recon History
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    No Cash &amp; Bank accounts. Mark COA with <strong>Is Cash/Bank</strong> first.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($accounts->isNotEmpty())
                        <tfoot>
                            <tr class="fin-row-grand">
                                <td colspan="3">Total Cash</td>
                                <td class="text-end">{{ format_number($totalBalance, 2, true) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <form method="GET" action="{{ route('finance.cash-bank.index.view') }}" id="filterForm">
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
            <a href="{{ route('finance.cash-bank.index.view') }}" class="btn btn-label-dark">Reset</a>
            <button type="submit" form="filterForm" class="btn btn-primary">Apply</button>
        </x-slot:footer>
    </x-modal>

    @if($hasUpdate && $companyId)
        <x-modal id="reconModal" title="Start Reconciliation">
            <form method="POST" action="{{ route('finance.cash-bank.reconciliation.start') }}" id="reconForm">
                @csrf
                <input type="hidden" name="company_id" value="{{ $companyId }}">
                <input type="hidden" name="account_id" id="recon_account_id">
                <div class="mb-3">
                    <label class="form-label">Account</label>
                    <input type="text" class="form-control" id="recon_account_name" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reconciliation Date</label>
                    <input type="text" name="reconciliation_date" class="form-control flatpickr-date"
                        value="{{ $asOf }}" placeholder="DD/MM/YYYY" required>
                </div>
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="reconForm" class="btn btn-primary">Continue</button>
            </x-slot:footer>
        </x-modal>
    @endif

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', disableMobile: true, allowInput: true });
                $('#reconModal').on('show.bs.modal', function(e) {
                    var btn = $(e.relatedTarget);
                    $('#recon_account_id').val(btn.data('account-id'));
                    $('#recon_account_name').val(btn.data('account-name'));
                });
            });
        </script>
    @endpush
</x-app-layout>
