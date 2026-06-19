<x-app-layout>
    @section('title', 'Gudang | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush
    @push('page-css')
        <style>
            .warehouse-table th, .warehouse-table td { vertical-align: middle; }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $isSuperAdmin = auth('web')->user()?->is_super_admin;
            $hasUpdatePermission = $isSuperAdmin || session('permissions.Warehouse.is_update', false) == 1;
            $hasDeletePermission = $isSuperAdmin || session('permissions.Warehouse.is_delete', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
            $typeLabels = ['WIP' => 'WIP', 'FG' => 'Barang Jadi', 'GENERAL' => 'Umum', 'TRANSIT' => 'Transit'];
            $scope = $scope ?? request('scope', 'all');
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Business'],
            ['label' => 'Gudang', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('warning'))<x-alert type="warning" class="mb-3">{{ session('warning') }}</x-alert>@endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Manajemen Gudang</h5>
                <div>
                    @permission('Warehouse', 'is_create')
                    <a href="{{ route('warehouse.insert.view') }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i> Tambah Gudang
                    </a>
                    @endpermission
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach([
                        'all' => 'Semua',
                        'distributor' => 'Distributor',
                        'branch' => 'Cabang',
                        'shared' => 'Shared',
                    ] as $key => $label)
                        <a href="{{ route('warehouse.index.view', array_filter(['scope' => $key === 'all' ? null : $key, 'status' => $status ?: null])) }}"
                           class="btn btn-sm btn-{{ $scope === $key ? 'primary' : 'label-secondary' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered warehouse-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px" class="text-center">No</th>
                                <th>Nama</th>
                                <th>Kode</th>
                                <th>Tipe</th>
                                <th>Cakupan</th>
                                <th>Cabang Terkait</th>
                                <th class="text-center">Inventory</th>
                                <th class="text-center">Aktif</th>
                                @if($hasAnyActionPermission)<th class="text-center">Aksi</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @php $rowNumber = 1; @endphp
                            @forelse ($parentCompanies as $parent)
                                <tr class="table-secondary">
                                    <td></td>
                                    <td colspan="{{ $hasAnyActionPermission ? 8 : 7 }}">
                                        <strong><span class="badge bg-label-info me-1">COMPANY</span>{{ $parent->name }}</strong>
                                        @if($parent->children->count())
                                            <span class="badge bg-label-secondary ms-2">{{ $parent->children->count() }} gudang</span>
                                        @endif
                                    </td>
                                </tr>
                                @forelse ($parent->children as $wh)
                                    @php
                                        $warehouseScope = $wh->branch_id
                                            ? ['label' => 'Cabang', 'color' => 'success']
                                            : ($wh->branches->isNotEmpty()
                                                ? ['label' => 'Shared', 'color' => 'warning']
                                                : ['label' => 'Distributor', 'color' => 'info']);
                                    @endphp
                                    <tr>
                                        <td class="text-center">{{ $rowNumber++ }}</td>
                                        <td><span class="text-muted">└</span> {{ $wh->name }}</td>
                                        <td><code>{{ $wh->code }}</code></td>
                                        <td>{{ $typeLabels[$wh->brand_name] ?? ($wh->brand_name ?: '-') }}</td>
                                        <td><span class="badge bg-label-{{ $warehouseScope['color'] }}">{{ $warehouseScope['label'] }}</span></td>
                                        <td>
                                            @if($wh->branches->isNotEmpty())
                                                {{ $wh->branches->pluck('name')->implode(', ') }}
                                            @elseif($wh->branch)
                                                {{ $wh->branch->name }}
                                            @else
                                                <span class="text-muted">Distributor / Pusat</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <i class="ti ti-{{ $wh->is_inventory_active ? 'check text-success' : 'x text-muted' }}"></i>
                                        </td>
                                        <td class="text-center">
                                            <i class="ti ti-{{ $wh->is_active ? 'check text-success' : 'x text-muted' }}"></i>
                                        </td>
                                        @if($hasAnyActionPermission)
                                        <td class="text-center">
                                            @if($wh->deleted_at)
                                                @permission('Warehouse', 'is_delete')
                                                <button type="button" class="btn btn-sm btn-label-success" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="{{ $wh->id }}" data-name="{{ $wh->name }}">
                                                    <i class="ti ti-refresh me-1"></i> Restore
                                                </button>
                                                @endpermission
                                            @else
                                                @permission('Warehouse', 'is_update')
                                                <a href="{{ route('warehouse.edit.view', $wh->id) }}" class="btn btn-sm btn-label-warning me-1"><i class="ti ti-pencil"></i></a>
                                                @endpermission
                                                @permission('Warehouse', 'is_delete')
                                                <button type="button" class="btn btn-sm btn-label-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="{{ $wh->id }}" data-name="{{ $wh->name }}">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                                @endpermission
                                            @endif
                                        </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $hasAnyActionPermission ? 9 : 8 }}" class="text-center text-muted">Belum ada gudang di company ini.</td></tr>
                                @endforelse
                            @empty
                                <tr><td colspan="{{ $hasAnyActionPermission ? 9 : 8 }}" class="text-center">Tidak ada data company.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('warehouse.delete.data') }}">@csrf
                    <div class="modal-header"><h5 class="modal-title">Hapus Gudang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p>Hapus gudang <strong id="warehouse-name-deleted"></strong>?</p>
                        <input type="hidden" id="warehouse-id-deleted" name="warehouse_id_deleted">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="restoreModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('warehouse.restore.data') }}">@csrf
                    <div class="modal-header"><h5 class="modal-title">Restore Gudang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p>Pulihkan gudang <strong id="warehouse-name-restore"></strong>?</p>
                        <input type="hidden" id="warehouse-id-restore" name="warehouse_id_restored">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Restore</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('page-js')
    <script>
        $('#deleteModal').on('show.bs.modal', function(e) {
            const b = $(e.relatedTarget);
            $('#warehouse-id-deleted').val(b.data('id'));
            $('#warehouse-name-deleted').text(b.data('name'));
        });
        $('#restoreModal').on('show.bs.modal', function(e) {
            const b = $(e.relatedTarget);
            $('#warehouse-id-restore').val(b.data('id'));
            $('#warehouse-name-restore').text(b.data('name'));
        });
    </script>
    @endpush
</x-app-layout>
