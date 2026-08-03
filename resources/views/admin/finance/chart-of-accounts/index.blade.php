<x-app-layout>
    @section('title', 'Chart of Accounts | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        @include('admin.finance.chart-of-accounts._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report">
        @php
            $hasUpdatePermission = session('permissions.Chart of Accounts.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Chart of Accounts.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Chart of Accounts.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
            $selectedCompany = $companies->firstWhere('id', $companyId);

            $accountsCol = collect($treeRows)->map(fn ($r) => $r['account']);
            $headerCount = $accountsCol->where('is_header', true)->count();
            $detailCount = $accountsCol->where('is_header', false)->count();
            $cashBankCount = $accountsCol->where('is_cash_bank', true)->count();
            $typeCounts = [
                'asset' => $accountsCol->where('account_type', 'asset')->count(),
                'liability' => $accountsCol->where('account_type', 'liability')->count(),
                'equity' => $accountsCol->where('account_type', 'equity')->count(),
                'revenue' => $accountsCol->where('account_type', 'revenue')->count(),
                'expense' => $accountsCol->where('account_type', 'expense')->count(),
            ];
            $typeBadge = [
                'asset' => 'bg-label-primary text-primary',
                'liability' => 'bg-label-warning text-warning',
                'equity' => 'bg-label-success text-success',
                'revenue' => 'bg-label-info text-info',
                'expense' => 'bg-label-danger text-danger',
            ];
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Chart of Accounts', 'active' => true],
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
                        <i class="ti ti-sitemap"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-0">Chart of Accounts · tree view</div>
                        @if($selectedCompany)
                            <div class="fin-company">{{ $selectedCompany->name }}</div>
                        @else
                            <div class="fin-company text-muted">Pilih company</div>
                        @endif
                        @if($isFilter)
                            <span class="badge bg-label-warning mt-1">Filter aktif</span>
                        @endif
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }} btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @if($hasCreatePermission && $companyId)
                        <a href="{{ route('finance.chart-of-accounts.insert.view') }}" class="btn btn-label-primary btn-sm">
                            <i class="ti ti-plus me-1"></i> Add
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @if($companyId)
            <div class="row g-3 mb-3 fin-kpi">
                <div class="col-6 col-xl">
                    <div class="card">
                        <div class="card-body py-3">
                            <div class="fin-kpi-label">Total</div>
                            <div class="fin-kpi-value">{{ count($treeRows) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl">
                    <div class="card">
                        <div class="card-body py-3">
                            <div class="fin-kpi-label">Header</div>
                            <div class="fin-kpi-value text-info">{{ $headerCount }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl">
                    <div class="card">
                        <div class="card-body py-3">
                            <div class="fin-kpi-label">Detail</div>
                            <div class="fin-kpi-value text-primary">{{ $detailCount }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl">
                    <div class="card">
                        <div class="card-body py-3">
                            <div class="fin-kpi-label">Cash/Bank</div>
                            <div class="fin-kpi-value text-success">{{ $cashBankCount }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body py-3 d-flex flex-wrap gap-2 align-items-center">
                            @foreach(\App\Models\Accounting\ChartOfAccount::ACCOUNT_TYPES as $code => $label)
                                <span class="coa-type-chip {{ $typeBadge[$code] ?? 'bg-label-secondary' }}">
                                    {{ $label }} {{ $typeCounts[$code] ?? 0 }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card fin-section accent-primary">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Account Tree</h5>
                    <div class="fin-section-sub">
                        @if($companyId)
                            {{ count($treeRows) }} akun · indent = hierarki parent/child
                        @else
                            Pilih company lewat Filter untuk menampilkan COA
                        @endif
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fin-table table-hover coa-tree-table mb-0">
                    <thead>
                        <tr>
                            <th style="min-width: 300px;">Akun</th>
                            <th style="width: 12%">Tipe</th>
                            <th style="width: 12%">Cash Flow</th>
                            <th style="width: 10%">Jenis</th>
                            <th style="width: 14%">Flags</th>
                            <th style="width: 10%">Aktif</th>
                            @if($hasAnyActionPermission)<th class="text-end pe-3" style="width: 100px;">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($treeRows as $row)
                            @php
                                /** @var \App\Models\Accounting\ChartOfAccount $account */
                                $account = $row['account'];
                                $depth = (int) $row['depth'];
                                $typeLabel = \App\Models\Accounting\ChartOfAccount::ACCOUNT_TYPES[$account->account_type] ?? $account->account_type;
                                $cfLabel = $account->cashFlowCategory?->name ?? '—';
                                $label = $account->code.' — '.$account->name;
                                $typeClass = 'coa-type-'.($account->account_type ?: 'asset');
                                $rowClass = trim(
                                    ($account->is_header ? 'coa-header-row ' : '').
                                    $typeClass.' '.
                                    ($account->trashed() ? 'table-danger ' : '').
                                    ((! $account->is_active && ! $account->trashed()) ? 'coa-inactive' : '')
                                );
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>
                                    <span style="padding-left: {{ $depth * 1.15 }}rem; display: inline-block;">
                                        @if($depth > 0)
                                            <span class="coa-indent-guide">└</span>
                                        @endif
                                        <span class="fin-account-code">{{ $account->code }}</span>
                                        <span class="{{ $account->is_header ? 'fw-semibold' : '' }}">{{ $account->name }}</span>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $typeBadge[$account->account_type] ?? 'bg-label-secondary' }}">
                                        {{ $typeLabel }}
                                    </span>
                                </td>
                                <td>
                                    <span class="small {{ $cfLabel === '—' ? 'text-muted' : '' }}">{{ $cfLabel }}</span>
                                </td>
                                <td>
                                    @if($account->is_header)
                                        <span class="badge bg-label-info">Header</span>
                                    @else
                                        <span class="badge bg-label-secondary">Detail</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @if($account->is_cash_bank)
                                            <span class="badge bg-label-primary">Cash/Bank</span>
                                        @endif
                                        @if($account->is_contra_account)
                                            <span class="badge bg-label-warning">Contra</span>
                                        @endif
                                        @if(! $account->is_cash_bank && ! $account->is_contra_account)
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($account->trashed())
                                        <span class="badge bg-label-danger">Deleted</span>
                                    @elseif($account->is_active)
                                        <span class="badge bg-label-success">Aktif</span>
                                    @else
                                        <span class="badge bg-label-secondary">Nonaktif</span>
                                    @endif
                                </td>
                                @if($hasAnyActionPermission)
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1 justify-content-end">
                                            @if($hasUpdatePermission && ! $account->trashed())
                                                <a href="{{ route('finance.chart-of-accounts.edit.view', $account->id) }}"
                                                    class="btn btn-sm btn-icon btn-label-warning" title="Edit">
                                                    <i class="ti ti-pencil"></i>
                                                </a>
                                            @endif
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-sm btn-icon btn-label-primary dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                    <i class="ti ti-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    @if($hasUpdatePermission && ! $account->trashed())
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('finance.chart-of-accounts.edit.view', $account->id) }}">
                                                                <i class="ti ti-pencil me-2 text-warning"></i>Edit
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @if($hasDeletePermission)
                                                        @if($account->trashed())
                                                            <li>
                                                                <button type="button" class="dropdown-item"
                                                                    data-bs-toggle="modal" data-bs-target="#restoreModal"
                                                                    data-id="{{ $account->id }}" data-name="{{ $label }}">
                                                                    <i class="ti ti-refresh me-2 text-success"></i>Restore
                                                                </button>
                                                            </li>
                                                        @else
                                                            <li>
                                                                <button type="button" class="dropdown-item"
                                                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                                    data-id="{{ $account->id }}" data-name="{{ $label }}">
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
                                <td colspan="{{ $hasAnyActionPermission ? 7 : 6 }}" class="text-center text-muted py-5">
                                    @if($companyId)
                                        Belum ada Chart of Accounts. Gunakan <strong>Add</strong> untuk menambah akun.
                                    @else
                                        Pilih company lewat Filter.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter Chart of Accounts">
        <form method="GET" action="{{ route('finance.chart-of-accounts.index.view') }}" id="filterForm">
            <div class="mb-3">
                <label class="form-label" for="filter_company_id">Company</label>
                <select name="company_id" id="filter_company_id" class="form-select select2">
                    @forelse($companies as $c)
                        <option value="{{ $c->id }}" @selected($companyId == $c->id)>{{ $c->name }}</option>
                    @empty
                        <option value="">Tidak ada company</option>
                    @endforelse
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="filter_q">Cari</label>
                <input type="text" name="q" id="filter_q" class="form-control" value="{{ $search }}" placeholder="Kode / nama">
            </div>
            <div class="mb-3">
                <label class="form-label" for="filter_account_type">Tipe Akun</label>
                <select name="account_type" id="filter_account_type" class="form-select select2" data-allow-clear="true">
                    <option value="">All</option>
                    @foreach(\App\Models\Accounting\ChartOfAccount::ACCOUNT_TYPES as $code => $label)
                        <option value="{{ $code }}" @selected($accountType === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="filter_status">Status</label>
                <select name="status" id="filter_status" class="form-select select2">
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    <option value="deleted" @selected($status === 'deleted')>Deleted</option>
                    <option value="all" @selected($status === 'all')>All</option>
                </select>
            </div>
        </form>
        <x-slot:footer>
            <a href="{{ route('finance.chart-of-accounts.index.view') }}" class="btn btn-label-dark">Reset</a>
            <button type="submit" form="filterForm" class="btn btn-primary">Terapkan</button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('finance.chart-of-accounts.delete.data')" confirmText="Submit">
        <p>Hapus akun <strong id="coa-name-deleted"></strong>?</p>
        <input type="hidden" id="chart-of-account-id-deleted" name="chart_of_account_id_deleted" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('finance.chart-of-accounts.restore.data')" confirmText="Submit">
        <p>Restore akun <strong id="coa-name-restore"></strong>?</p>
        <input type="hidden" id="chart-of-account-id-restore" name="chart_of_account_id_restored" />
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
                    $('#chart-of-account-id-deleted').val(btn.data('id'));
                    $('#coa-name-deleted').text(btn.data('name'));
                });
                $('#restoreModal').on('show.bs.modal', function(e) {
                    var btn = $(e.relatedTarget);
                    $('#chart-of-account-id-restore').val(btn.data('id'));
                    $('#coa-name-restore').text(btn.data('name'));
                });
            });
        </script>
    @endpush
</x-app-layout>
