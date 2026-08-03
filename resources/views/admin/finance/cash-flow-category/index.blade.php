<x-app-layout>
    @section('title', 'Cash Flow Category | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report">
        @php
            $hasUpdatePermission = session('permissions.Cash Flow Category.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Cash Flow Category.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Cash Flow Category.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
            $activeCount = $categories->filter(fn ($c) => ! $c->trashed() && $c->is_active)->count();
            $inactiveCount = $categories->filter(fn ($c) => ! $c->trashed() && ! $c->is_active)->count();
            $deletedCount = $categories->filter(fn ($c) => $c->trashed())->count();
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Cash Flow Category', 'active' => true],
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
                    <div class="fin-kpi-icon bg-label-info text-info"><i class="ti ti-arrows-exchange"></i></div>
                    <div>
                        <div class="text-muted small mb-0">Master kategori arus kas untuk Chart of Accounts</div>
                        <div class="fin-company">Cash Flow Category</div>
                        @if($isFilter)<span class="badge bg-label-warning mt-1">Filter aktif</span>@endif
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }} btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @if($hasCreatePermission)
                        <a href="{{ route('finance.cash-flow-category.insert.view') }}" class="btn btn-label-primary btn-sm">
                            <i class="ti ti-plus me-1"></i> Add
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3 fin-kpi">
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Total</div><div class="fin-kpi-value">{{ $categories->count() }}</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Aktif</div><div class="fin-kpi-value text-success">{{ $activeCount }}</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Nonaktif</div><div class="fin-kpi-value text-muted">{{ $inactiveCount }}</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="fin-kpi-label">Deleted</div><div class="fin-kpi-value text-danger">{{ $deletedCount }}</div></div></div>
            </div>
        </div>

        <div class="card fin-section accent-info">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Categories</h5>
                    <div class="fin-section-sub">{{ $categories->count() }} data · dipakai di COA &amp; Cash Flow report</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fin-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 14%">Kode</th>
                            <th>Nama</th>
                            <th style="width: 10%">Urutan</th>
                            <th style="width: 12%">Aktif</th>
                            @if($hasAnyActionPermission)<th class="text-end pe-3" style="width: 100px;">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr class="{{ $category->trashed() ? 'table-danger' : '' }}">
                                <td><span class="fin-account-code">{{ $category->code }}</span></td>
                                <td>
                                    <div class="fw-medium">{{ $category->name }}</div>
                                    @if($category->description)
                                        <div class="text-muted small">{{ $category->description }}</div>
                                    @endif
                                </td>
                                <td class="fin-amount">{{ $category->sort_order }}</td>
                                <td>
                                    @if($category->trashed())
                                        <span class="badge bg-label-danger">Deleted</span>
                                    @elseif($category->is_active)
                                        <span class="badge bg-label-success">Aktif</span>
                                    @else
                                        <span class="badge bg-label-secondary">Nonaktif</span>
                                    @endif
                                </td>
                                @if($hasAnyActionPermission)
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            @if($hasUpdatePermission && ! $category->trashed())
                                                <a href="{{ route('finance.cash-flow-category.edit.view', $category->id) }}" class="btn btn-sm btn-icon btn-label-warning" title="Edit">
                                                    <i class="ti ti-pencil"></i>
                                                </a>
                                            @endif
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-sm btn-icon btn-label-primary dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                    <i class="ti ti-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    @if($hasUpdatePermission && ! $category->trashed())
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('finance.cash-flow-category.edit.view', $category->id) }}">
                                                                <i class="ti ti-pencil me-2 text-warning"></i>Edit
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @if($hasDeletePermission)
                                                        @if($category->trashed())
                                                            <li>
                                                                <button type="button" class="dropdown-item"
                                                                    data-bs-toggle="modal" data-bs-target="#restoreModal"
                                                                    data-id="{{ $category->id }}" data-name="{{ $category->displayLabel() }}">
                                                                    <i class="ti ti-refresh me-2 text-success"></i>Restore
                                                                </button>
                                                            </li>
                                                        @else
                                                            <li>
                                                                <button type="button" class="dropdown-item"
                                                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                                    data-id="{{ $category->id }}" data-name="{{ $category->displayLabel() }}">
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
                                <td colspan="{{ $hasAnyActionPermission ? 5 : 4 }}" class="text-center text-muted py-5">Belum ada data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <form method="GET" action="{{ route('finance.cash-flow-category.index.view') }}" id="filterForm">
            <div class="mb-3">
                <label class="form-label" for="filter_q">Cari</label>
                <input type="text" name="q" id="filter_q" class="form-control" value="{{ $search }}" placeholder="Kode / nama">
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
            <a href="{{ route('finance.cash-flow-category.index.view') }}" class="btn btn-label-dark">Reset</a>
            <button type="submit" form="filterForm" class="btn btn-primary">Terapkan</button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('finance.cash-flow-category.delete.data')" confirmText="Submit">
        <p>Hapus kategori <strong id="cfc-name-deleted"></strong>?</p>
        <input type="hidden" id="cash-flow-category-id-deleted" name="cash_flow_category_id_deleted" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('finance.cash-flow-category.restore.data')" confirmText="Submit">
        <p>Restore kategori <strong id="cfc-name-restore"></strong>?</p>
        <input type="hidden" id="cash-flow-category-id-restore" name="cash_flow_category_id_restored" />
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
                    $('#cash-flow-category-id-deleted').val(btn.data('id'));
                    $('#cfc-name-deleted').text(btn.data('name'));
                });
                $('#restoreModal').on('show.bs.modal', function(e) {
                    var btn = $(e.relatedTarget);
                    $('#cash-flow-category-id-restore').val(btn.data('id'));
                    $('#cfc-name-restore').text(btn.data('name'));
                });
            });
        </script>
    @endpush
</x-app-layout>
