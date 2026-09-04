<x-app-layout>
    @section('title', 'Price Lists | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush
    @push('page-css')
        <style>.breadcrumb-item a:hover { color: #212529 !important; }</style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Product.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Product.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Product.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Price Lists', 'active' => true],
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
                            <th style="width: 50px;">No</th>
                            <th style="width: 100px;">Code</th>
                            <th>Name</th>
                            <th style="width: 150px;">Channel Type</th>
                            <th style="width: 120px;">Ext. Code</th>
                            <th style="width: 80px;">Order</th>
                            <th style="width: 80px;">Active</th>
                            <th style="width: 80px;">Status</th>
                            @if($hasAnyActionPermission)<th style="width: 60px;">Actions</th>@endif
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

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('product.price-list.delete.data')" confirmText="Submit">
        <p>Are you sure you want to delete <strong id="price-list-name-deleted"></strong>?</p>
        <input type="hidden" id="price-list-id-deleted" name="price_list_id_deleted" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('product.price-list.restore.data')" confirmText="Submit">
        <p>Are you sure you want to restore <strong id="price-list-name-restore"></strong>?</p>
        <input type="hidden" id="price-list-id-restore" name="price_list_id_restored" />
    </x-confirm-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.js') }}"></script>
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
                    autoWidth: false,
                    ajax: { url: "{{ route('product.price-list.index.data') }}", type: "POST", data: { _token: "{{ csrf_token() }}", status: "{{ $status }}" } },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'code', orderable: true, searchable: true },
                        { data: 'name', orderable: true, searchable: true },
                        {
                            data: 'channel_type',
                            render: function(d) {
                                if (d === 'pos') return '<span class="badge bg-label-primary">POS / Kasir</span>';
                                if (d === 'marketplace') return '<span class="badge bg-label-info">Marketplace</span>';
                                if (d === 'delivery') return '<span class="badge bg-label-warning">Delivery / Ojol</span>';
                                return '<span class="badge bg-label-secondary">-</span>';
                            }
                        },
                        { data: 'external_channel_code', orderable: true, searchable: true },
                        { data: 'sort_order', orderable: true, searchable: false },
                        {
                            data: 'is_active',
                            render: function(d) {
                                return d ? '<span class="badge bg-label-success">Yes</span>' : '<span class="badge bg-label-danger">No</span>';
                            }
                        },
                        {
                            data: 'deleted_at',
                            render: function(d) {
                                return d ? '<span class="badge bg-label-danger">Deleted</span>' : '<span class="badge bg-label-success">Active</span>';
                            }
                        },
                        @if($hasAnyActionPermission){ data: null, orderable: false, searchable: false, render: function(d,t,r) {
                            var html = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                            if (@json($hasUpdatePermission)) html += '<li><a class="dropdown-item" href="{{ url("product/price-list/edit") }}/'+r.id+'"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>';
                            if (@json($hasDeletePermission)) html += r.deleted_at ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="'+r.id+'" data-name="'+r.name+'"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>' : '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="'+r.id+'" data-name="'+r.name+'"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>';
                            return html + '</ul></div>';
                        } }@endif
                    ],
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $status != 'active' ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } },
                        @if($hasCreatePermission){ text: '<i class="ti ti-plus me-sm-1"></i> Add', className: "btn btn-primary", action: function() { window.location = '{{ route("product.price-list.insert.view") }}'; } }@endif
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Price Lists</h4>');
                $("#btnFilter").click(function() { var s=$('#selectStatus').val(); window.location = '/product/price-list' + (s ? '?status='+s : ''); });
                $("#btnResetFilter").click(function() { window.location = '/product/price-list'; });
                $('#deleteModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#price-list-id-deleted').val(b.data('id')); $('#price-list-name-deleted').text(b.data('name')); });
                $('#restoreModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#price-list-id-restore').val(b.data('id')); $('#price-list-name-restore').text(b.data('name')); });
            });
        </script>
    @endpush
</x-app-layout>
