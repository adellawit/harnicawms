<x-app-layout>
    @section('title', 'Product Unit | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Unit.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Unit.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Unit.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Unit', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        <div class="card">
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered" id="table">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Symbol</th>
                            <th>Created At</th>
                            <th>Status</th>
                            @if($hasAnyActionPermission)<th>Actions</th>@endif
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <label class="form-label">Status</label>
        <select id="selectStatus" class="select2 form-select" data-allow-clear="true">
            <option value="">All</option>
            <option value="active" @if($status=='active') selected @endif>Active</option>
            <option value="deleted" @if($status=='deleted') selected @endif>Deleted</option>
        </select>
        <x-slot:footer>
            <button type="button" class="btn btn-label-dark" id="btnResetFilter">Reset</button>
            <button type="button" class="btn btn-primary" id="btnFilter" data-bs-dismiss="modal">Filter</button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('product.unit.delete.data')" confirmText="Submit">
        <p>Are you sure you want to delete <strong id="unit-name-deleted"></strong>?</p>
        <input type="hidden" id="unit-id-deleted" name="unit_id_deleted" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('product.unit.restore.data')" confirmText="Submit">
        <p>Are you sure you want to restore <strong id="unit-name-restore"></strong>?</p>
        <input type="hidden" id="unit-id-restore" name="unit_id_restored" />
    </x-confirm-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('#table').DataTable({
                    processing: true, serverSide: true, paging: true, scrollX: true,
                    ajax: { url: "{{ route('product.unit.index.data') }}", type: "POST", data: { _token: "{{ csrf_token() }}", status: "{{ $status }}" } },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'code' },
                        { data: 'name' },
                        { data: 'symbol', render: function(d) { return d || '-'; } },
                        { data: 'created_at', render: function(d) { return d ? moment(d).format("DD MMM YYYY - HH:mm") : '-'; } },
                        { data: 'deleted_at', render: function(d) { return d ? '<span class="badge bg-label-danger">Deleted</span>' : '<span class="badge bg-label-success">Active</span>'; } },
                        @if($hasAnyActionPermission){ data: null, orderable: false, searchable: false, render: function(d,t,r) {
                            var html = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                            if (@json($hasUpdatePermission)) html += '<li><a class="dropdown-item" href="{{ url("product/satuan/edit") }}/'+r.id+'"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>';
                            if (@json($hasDeletePermission)) html += r.deleted_at ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="'+r.id+'" data-name="'+r.name+'"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>' : '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="'+r.id+'" data-name="'+r.name+'"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>';
                            return html + '</ul></div>';
                        } }@endif
                    ],
                    dom: '<"card-header flex-column flex-md-row"<"head-label text-center"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row m-0"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } },
                        @if($hasCreatePermission){ text: '<i class="ti ti-plus me-sm-1"></i> Add', className: "btn btn-primary", action: function() { window.location = '{{ route("product.unit.insert.view") }}'; } }@endif
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Product Unit</h4>');
                $("#btnFilter").click(function() { window.location = '/product/satuan' + ($('#selectStatus').val() ? '?status='+$('#selectStatus').val() : ''); });
                $("#btnResetFilter").click(function() { window.location = '/product/satuan'; });
                $('#deleteModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#unit-id-deleted').val(b.data('id')); $('#unit-name-deleted').text(b.data('name')); });
                $('#restoreModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#unit-id-restore').val(b.data('id')); $('#unit-name-restore').text(b.data('name')); });
            });
        </script>
    @endpush
</x-app-layout>
