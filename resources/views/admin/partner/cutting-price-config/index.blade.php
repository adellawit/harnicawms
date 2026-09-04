<x-app-layout>
    @section('title', 'Cutting Price Config | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Cutting Price Config.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Cutting Price Config.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Cutting Price Config.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header
            title="Cutting Price Config"
            subtitle="Konfigurasi per kategori: H.K. Resmi, MAP, Reseller (30/60/120), Agen (600). Tidak terikat price list."
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Network', 'url' => route('partner.reports.index')],
                ['label' => 'Cutting Price Config', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered" id="table">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Kategori</th>
                            <th>Unit</th>
                            <th>H.K. Resmi</th>
                            <th>MAP</th>
                            <th>Reseller 30</th>
                            <th>Reseller 60</th>
                            <th>Reseller 120</th>
                            <th>Agen 600</th>
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
        <x-slot:footer>
            <button type="button" class="btn btn-label-dark" id="btnResetFilter">Reset</button>
            <button type="button" class="btn btn-primary" id="btnFilter" data-bs-dismiss="modal">Filter</button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('partner.cutting-price-config.delete.data')" confirmText="Submit">
        <p>Hapus config <strong id="config-name-deleted"></strong>?</p>
        <input type="hidden" id="cutting-price-config-id-deleted" name="cutting_price_config_id_deleted" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('partner.cutting-price-config.restore.data')" confirmText="Submit">
        <p>Restore config <strong id="config-name-restore"></strong>?</p>
        <input type="hidden" id="cutting-price-config-id-restore" name="cutting_price_config_id_restored" />
    </x-confirm-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive/datatables.responsive.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('#table').DataTable({
                    processing: true, serverSide: true, paging: true,  
                    ajax: {
                        url: "{{ route('partner.cutting-price-config.index.data') }}",
                        type: "POST",
                        data: { _token: "{{ csrf_token() }}", status: "{{ $status }}" }
                    },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'category_label', orderable: false, searchable: true },
                        { data: 'unit_code' },
                        { data: 'official_price', className: 'text-end', render: function(d) {
                            return Number(d).toLocaleString('id-ID');
                        }},
                        { data: 'map_price', className: 'text-end', render: function(d) {
                            return Number(d).toLocaleString('id-ID');
                        }},
                        { data: 'reseller_price_30', className: 'text-end', render: function(d) {
                            return d == null ? '-' : Number(d).toLocaleString('id-ID');
                        }},
                        { data: 'reseller_price_60', className: 'text-end', render: function(d) {
                            return d == null ? '-' : Number(d).toLocaleString('id-ID');
                        }},
                        { data: 'reseller_price_120', className: 'text-end', render: function(d) {
                            return d == null ? '-' : Number(d).toLocaleString('id-ID');
                        }},
                        { data: 'agent_price_600', className: 'text-end', render: function(d) {
                            return d == null ? '-' : Number(d).toLocaleString('id-ID');
                        }},
                        { data: 'is_active', render: function(d) {
                            return d ? '<span class="badge bg-label-success">Ya</span>' : '<span class="badge bg-label-secondary">Tidak</span>';
                        }},
                        { data: 'deleted_at', render: function(d) {
                            return d ? '<span class="badge bg-label-danger">Deleted</span>' : '<span class="badge bg-label-success">Active</span>';
                        }},
                        @if($hasAnyActionPermission){ data: null, orderable: false, searchable: false, render: function(d,t,r) {
                            var label = r.category_label || r.id;
                            var html = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                            if (@json($hasUpdatePermission)) html += '<li><a class="dropdown-item" href="{{ url("partner-network/cutting-price-config/edit") }}/'+r.id+'"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>';
                            if (@json($hasDeletePermission)) html += r.deleted_at
                                ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="'+r.id+'" data-name="'+label+'"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>'
                                : '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="'+r.id+'" data-name="'+label+'"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>';
                            return html + '</ul></div>';
                        } }@endif
                    ],
                    language: { emptyTable: "No data available in table", zeroRecords: "No data available in table" },
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } },
                        @if($hasCreatePermission){ text: '<i class="ti ti-plus me-sm-1"></i> Add', className: "btn btn-primary", action: function() { window.location = '{{ route("partner.cutting-price-config.insert.view") }}'; } }@endif
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Cutting Price Config</h4>');
                $("#btnFilter").click(function() {
                    var s = $('#selectStatus').val();
                    window.location = '{{ route("partner.cutting-price-config.index.view") }}' + (s ? '?status=' + encodeURIComponent(s) : '');
                });
                $("#btnResetFilter").click(function() { window.location = '{{ route("partner.cutting-price-config.index.view") }}'; });
                $('#deleteModal').on('show.bs.modal', function(e) {
                    var b = $(e.relatedTarget);
                    $('#cutting-price-config-id-deleted').val(b.data('id'));
                    $('#config-name-deleted').text(b.data('name'));
                });
                $('#restoreModal').on('show.bs.modal', function(e) {
                    var b = $(e.relatedTarget);
                    $('#cutting-price-config-id-restore').val(b.data('id'));
                    $('#config-name-restore').text(b.data('name'));
                });
            });
        </script>
    @endpush
</x-app-layout>
