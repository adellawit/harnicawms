<x-app-layout>
    @section('title', 'Customer Group | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush
    @push('page-css')
        <style>.breadcrumb-item a:hover { color: #212529 !important; }</style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Group.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Group.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Group.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Customer'],
                ['label' => 'Group', 'active' => true],
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
                            <th>Branch</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Price List</th>
                            <th>Discount %</th>
                            <th>Credit</th>
                            <th>Term (days)</th>
                            <th>Points</th>
                            <th>Active</th>
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

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('customer.group.delete.data')" confirmText="Delete">
        <p>Are you sure you want to delete <strong id="group-name-deleted"></strong>?</p>
        <input type="hidden" id="group-id-deleted" name="customer_group_id_deleted" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('customer.group.restore.data')" confirmText="Restore">
        <p>Are you sure you want to restore <strong id="group-name-restore"></strong>?</p>
        <input type="hidden" id="group-id-restore" name="customer_group_id_restored" />
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
                    ajax: { url: "{{ route('customer.group.index.data') }}", type: "POST", data: { _token: "{{ csrf_token() }}", status: "{{ $status }}" } },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'branch_name', orderable: true, searchable: false, render: function(d) { return d || '-'; } },
                        { data: 'code', orderable: true, searchable: true, render: function(d) { return d || '-'; } },
                        { data: 'name', orderable: true, searchable: true },
                        { data: 'price_list_name', orderable: false, searchable: false, render: function(d) { return d || '-'; } },
                        { data: 'default_discount', orderable: true, searchable: false, render: function(d) { return d != null ? Number(d).toFixed(2) + '%' : '-'; } },
                        { data: null, orderable: false, searchable: false, render: function(d,t,r) { return r.allow_credit ? (r.credit_limit ? Number(r.credit_limit).toLocaleString() : 'Yes') : '-'; } },
                        { data: 'payment_term_days', orderable: true, searchable: false, render: function(d) { return d != null && d > 0 ? d : '-'; } },
                        { data: null, orderable: false, searchable: false, render: function(d,t,r) { return r.earn_point ? 'x' + (r.point_multiplier || 1) : '-'; } },
                        { data: 'is_active', orderable: true, searchable: false, render: function(d) { return d ? '<span class="badge bg-label-success">Yes</span>' : '<span class="badge bg-label-secondary">No</span>'; } },
                        { data: 'created_at', render: function(d) { return d ? moment(d).format("DD MMM YYYY - HH:mm") : '-'; } },
                        { data: 'deleted_at', render: function(d) { return d ? '<span class="badge bg-label-danger">Deleted</span>' : '<span class="badge bg-label-success">Active</span>'; } },
                        @if($hasAnyActionPermission){ data: null, orderable: false, searchable: false, render: function(d,t,r) {
                            var html = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                            if (@json($hasUpdatePermission)) html += '<li><a class="dropdown-item" href="{{ url("customer/group/edit") }}/'+r.id+'"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>';
                            if (@json($hasDeletePermission)) html += r.deleted_at ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="'+r.id+'" data-name="'+r.name+'"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>' : '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="'+r.id+'" data-name="'+r.name+'"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>';
                            return html + '</ul></div>';
                        } }@endif
                    ],
                    language: { emptyTable: "No data available", zeroRecords: "Tidak ada data yang cocok dengan pencarian" },
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } },
                        @if($hasCreatePermission){ text: '<i class="ti ti-plus me-sm-1"></i> Add', className: "btn btn-primary", action: function() { window.location = '{{ route("customer.group.insert.view") }}'; } }@endif
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Customer Group</h4>');
                $("#btnFilter").click(function() { var s=$('#selectStatus').val(); window.location = '{{ route("customer.group.index") }}' + (s ? '?status='+s : ''); });
                $("#btnResetFilter").click(function() { window.location = '{{ route("customer.group.index") }}'; });
                $('#deleteModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#group-id-deleted').val(b.data('id')); $('#group-name-deleted').text(b.data('name')); });
                $('#restoreModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#group-id-restore').val(b.data('id')); $('#group-name-restore').text(b.data('name')); });
            });
        </script>
    @endpush
</x-app-layout>
