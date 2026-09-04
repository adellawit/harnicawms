<x-app-layout>
    @section('title', 'Master Ongkir | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Master Ongkir.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Master Ongkir.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Master Ongkir.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Master Data'],
            ['label' => 'Master Ongkir', 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if (session('import_errors'))
            <x-alert type="warning" class="mb-3">
                <div class="fw-semibold mb-1">Beberapa baris gagal diimport:</div>
                <ul class="mb-0">
                    @foreach (session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="text-muted small mb-0">
                    Estimasi: <code>base + ceil(kg) × per_kg</code>.
                </div>
                @if($hasCreatePermission)
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('shipping-rate.import.template') }}" class="btn btn-outline-primary btn-sm">
                            <i class="ti ti-download me-1"></i> Template CSV
                        </a>
                        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="ti ti-upload me-1"></i> Import CSV
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered" id="table">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Kurir</th>
                            <th>Layanan</th>
                            <th>Base</th>
                            <th>Per Kg</th>
                            <th>ETD</th>
                            <th>Aktif</th>
                            <th>Status</th>
                            @if($hasAnyActionPermission)<th>Actions</th>@endif
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select id="selectStatus" class="select2 form-select" data-allow-clear="true">
                <option value="">All</option>
                <option value="active" @if($status=='active') selected @endif>Active</option>
                <option value="deleted" @if($status=='deleted') selected @endif>Deleted</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Kurir</label>
            <select id="selectCourier" class="select2 form-select" data-allow-clear="true">
                <option value="">All</option>
                @foreach(\App\Models\ShippingRate::COURIERS as $code => $label)
                    <option value="{{ $code }}" @if($courier===$code) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-label-dark" id="btnResetFilter">Reset</button>
            <button type="button" class="btn btn-primary" id="btnFilter" data-bs-dismiss="modal">Filter</button>
        </x-slot:footer>
    </x-modal>

    <x-modal id="importModal" title="Import CSV Ongkir">
        <form method="POST" action="{{ route('shipping-rate.import.data') }}" enctype="multipart/form-data" id="importForm">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="csv_file">File CSV</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,text/csv" required>
                <div class="form-text">Max 5MB. Duplikat (origin+dest+kurir+service) akan di-update.</div>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Batal</button>
            <button type="submit" form="importForm" class="btn btn-primary">Import</button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('shipping-rate.delete.data')" confirmText="Submit">
        <p>Hapus tarif <strong id="rate-name-deleted"></strong>?</p>
        <input type="hidden" id="shipping-rate-id-deleted" name="shipping_rate_id_deleted" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('shipping-rate.restore.data')" confirmText="Submit">
        <p>Restore tarif <strong id="rate-name-restore"></strong>?</p>
        <input type="hidden" id="shipping-rate-id-restore" name="shipping_rate_id_restored" />
    </x-confirm-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive/datatables.responsive.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('#table').DataTable({
                    processing: true, serverSide: true, paging: true,  
                    ajax: {
                        url: "{{ route('shipping-rate.index.data') }}",
                        type: "POST",
                        data: { _token: "{{ csrf_token() }}", status: "{{ $status }}", courier: "{{ $courier }}" }
                    },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'origin_label', orderable: false, searchable: true },
                        { data: 'destination_label', orderable: false, searchable: true },
                        { data: 'courier_label', orderable: false, searchable: true },
                        { data: 'service_code', render: function(d, t, r) {
                            return (r.service_name ? r.service_name + ' (' + d + ')' : d);
                        }},
                        { data: 'base_amount', render: function(d) { return Number(d).toLocaleString('id-ID'); } },
                        { data: 'per_kg_amount', render: function(d) { return Number(d).toLocaleString('id-ID'); } },
                        { data: null, orderable: false, searchable: false, render: function(d, t, r) {
                            if (r.etd_min_days == null && r.etd_max_days == null) return '-';
                            return (r.etd_min_days ?? '?') + '-' + (r.etd_max_days ?? '?') + ' hari';
                        }},
                        { data: 'is_active', render: function(d) {
                            return d ? '<span class="badge bg-label-success">Ya</span>' : '<span class="badge bg-label-secondary">Tidak</span>';
                        }},
                        { data: 'deleted_at', render: function(d) {
                            return d ? '<span class="badge bg-label-danger">Deleted</span>' : '<span class="badge bg-label-success">Active</span>';
                        }},
                        @if($hasAnyActionPermission){ data: null, orderable: false, searchable: false, render: function(d,t,r) {
                            var label = (r.origin_label || '') + ' → ' + (r.destination_label || '');
                            var html = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                            if (@json($hasUpdatePermission)) html += '<li><a class="dropdown-item" href="{{ url("master-data/shipping-rate/edit") }}/'+r.id+'"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>';
                            if (@json($hasDeletePermission)) html += r.deleted_at
                                ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="'+r.id+'" data-name="'+label+'"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>'
                                : '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="'+r.id+'" data-name="'+label+'"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>';
                            return html + '</ul></div>';
                        } }@endif
                    ],
                    language: { emptyTable: "No data available in table", zeroRecords: "No data available in table" },
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } },
                        @if($hasCreatePermission){ text: '<i class="ti ti-plus me-sm-1"></i> Add', className: "btn btn-primary", action: function() { window.location = '{{ route("shipping-rate.insert.view") }}'; } }@endif
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Master Ongkir</h4>');
                $("#btnFilter").click(function() {
                    var s = $('#selectStatus').val();
                    var c = $('#selectCourier').val();
                    var q = [];
                    if (s) q.push('status=' + encodeURIComponent(s));
                    if (c) q.push('courier=' + encodeURIComponent(c));
                    window.location = '{{ route("shipping-rate.index.view") }}' + (q.length ? '?' + q.join('&') : '');
                });
                $("#btnResetFilter").click(function() { window.location = '{{ route("shipping-rate.index.view") }}'; });
                $('#deleteModal').on('show.bs.modal', function(e) {
                    var b = $(e.relatedTarget);
                    $('#shipping-rate-id-deleted').val(b.data('id'));
                    $('#rate-name-deleted').text(b.data('name'));
                });
                $('#restoreModal').on('show.bs.modal', function(e) {
                    var b = $(e.relatedTarget);
                    $('#shipping-rate-id-restore').val(b.data('id'));
                    $('#rate-name-restore').text(b.data('name'));
                });
            });
        </script>
    @endpush
</x-app-layout>
