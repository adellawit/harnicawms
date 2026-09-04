<x-app-layout>
    @section('title', 'Customer List | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.List.is_update', false) == 1;
            $hasDeletePermission = session('permissions.List.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.List.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Customer'],
                ['label' => 'List', 'active' => true],
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
                            <th>Code</th>
                            <th>Name</th>
                            <th>Group</th>
                            <th>Branch</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Poin</th>
                            <th>Type</th>
                            <th>Partner</th>
                            <th>App Access</th>
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
            <select id="selectStatus" class="form-select">
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

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('customer.list.delete.data')" confirmText="Delete">
        <p>Are you sure you want to delete <strong id="customer-name-deleted"></strong>?</p>
        <input type="hidden" id="customer-id-deleted" name="customer_id_deleted" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('customer.list.restore.data')" confirmText="Restore">
        <p>Are you sure you want to restore <strong id="customer-name-restore"></strong>?</p>
        <input type="hidden" id="customer-id-restore" name="customer_id_restored" />
    </x-confirm-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive/datatables.responsive.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                function renderCustomerType(type) {
                    if (!type) return '-';
                    var labels = {
                        individual: 'Individual',
                        company: 'Company',
                        PARTNER_LEAD: 'Partner Lead',
                        AGENT: 'Agent',
                        RESELLER: 'Reseller'
                    };
                    var badges = {
                        PARTNER_LEAD: 'bg-label-warning',
                        AGENT: 'bg-label-success',
                        RESELLER: 'bg-label-info'
                    };
                    var label = labels[type] || type.charAt(0).toUpperCase() + type.slice(1);
                    var badgeClass = badges[type] || 'bg-label-secondary';
                    return '<span class="badge ' + badgeClass + '">' + label + '</span>';
                }

                function renderPartnerRole(role, row) {
                    if (role === 'agent') {
                        return '<span class="badge bg-label-success">Agent</span><div class="small text-muted">' + (row.partner_agent_code || '-') + '</div>';
                    }
                    if (role === 'reseller') {
                        return '<span class="badge bg-label-info">Reseller</span><div class="small text-muted">' + (row.partner_reseller_code || '-') + '</div>';
                    }
                    if (role === 'partner_lead') {
                        return '<span class="badge bg-label-warning">Lead</span>';
                    }
                    return '-';
                }

                $('#table').DataTable({
                    processing: true, serverSide: true, paging: true,  
                    ajax: { url: "{{ route('customer.list.index.data') }}", type: "POST", data: { _token: "{{ csrf_token() }}", status: "{{ $status }}" } },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'code', orderable: true, searchable: true },
                        { data: 'name', orderable: true, searchable: true },
                        { data: 'group_name', orderable: true, searchable: false, render: d => d || '-' },
                        { data: 'branch_name', orderable: true, searchable: false, render: d => d || '-' },
                        { data: 'contact_person', orderable: false, searchable: true, render: d => d || '-' },
                        { data: 'email', orderable: false, searchable: true, render: d => d || '-' },
                        { data: 'phone', orderable: false, searchable: true, render: d => d || '-' },
                        { data: 'points_balance', orderable: true, searchable: false, className: 'text-end', render: d => Number(d || 0).toLocaleString('id-ID') },
                        { data: 'customer_type', orderable: true, searchable: false, render: d => renderCustomerType(d) },
                        { data: 'partner_role', orderable: false, searchable: false, render: (d, t, r) => renderPartnerRole(d, r) },
                        { data: 'has_app_access', orderable: true, searchable: false, render: d => d ? '<span class="badge bg-label-success">Yes</span>' : '<span class="badge bg-label-secondary">No</span>' },
                        { data: 'is_active', orderable: true, searchable: false, render: d => d ? '<span class="badge bg-label-success">Yes</span>' : '<span class="badge bg-label-secondary">No</span>' },
                        { data: 'created_at', render: d => d ? moment(d).format("DD MMM YYYY") : '-' },
                        { data: 'deleted_at', render: d => d ? '<span class="badge bg-label-danger">Deleted</span>' : '<span class="badge bg-label-success">Active</span>' },
                        @if($hasAnyActionPermission){ data: null, orderable: false, searchable: false, render: function(d,t,r) {
                            var html = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                            if (@json($hasUpdatePermission)) html += '<li><a class="dropdown-item" href="{{ url("customer/list/edit") }}/'+r.id+'"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>';
                            if (@json($hasDeletePermission)) html += r.deleted_at ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="'+r.id+'" data-name="'+r.name+'"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>' : '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="'+r.id+'" data-name="'+r.name.replace(/'/g, "\\'")+'"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>';
                            return html + '</ul></div>';
                        } }@endif
                    ],
                    language: { emptyTable: "No data available", zeroRecords: "Tidak ada data yang cocok dengan pencarian" },
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } },
                        @if($hasCreatePermission){ text: '<i class="ti ti-plus me-sm-1"></i> Add', className: "btn btn-primary", action: function() { window.location = '{{ route("customer.list.insert.view") }}'; } }@endif
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Customer List</h4>');
                $("#btnFilter").click(function() { var s=$('#selectStatus').val(); window.location = '{{ route("customer.list.index") }}' + (s ? '?status='+s : ''); });
                $("#btnResetFilter").click(function() { window.location = '{{ route("customer.list.index") }}'; });
                $('#deleteModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#customer-id-deleted').val(b.data('id')); $('#customer-name-deleted').text(b.data('name')); });
                $('#restoreModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#customer-id-restore').val(b.data('id')); $('#customer-name-restore').text(b.data('name')); });
            });
        </script>
    @endpush
</x-app-layout>
