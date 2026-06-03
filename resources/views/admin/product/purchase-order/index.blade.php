<x-app-layout>
    @section('title', 'Purchase Order | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = true;
            $hasDeletePermission = true;
            $hasCreatePermission = true;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;

            $branches = \App\Models\BusinessUnit::where('is_active', true)
                ->where('type_code', 'BRANCH')
                ->orderBy('name')
                ->get(['id', 'name']);
            $defaultBranch = auth('web')->user()->current_business_unit_id;
            $selectedBranchId = request('branch_id', $filterBranchId ?? $defaultBranch);
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Purchase Order', 'active' => true],
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
                            <th>Purchase Number</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Status</th>
                            <th>Total</th>
                            @if($hasAnyActionPermission)<th>Actions</th>@endif
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <div class="mb-3">
            <label class="form-label">Branch</label>
            <select id="branch_id" class="select2 form-select" data-allow-clear="true">
                <option value="">All Branch</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ $selectedBranchId === $b->id ? 'selected' : '' }}>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>
        </div>
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

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('product.purchase-order.delete.data')" confirmText="Delete">
        <p class="mb-0">Are you sure you want to delete purchase order <strong id="po-number-deleted"></strong>?</p>
        <input type="hidden" id="po-id-deleted" name="id" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('product.purchase-order.restore.data')" confirmText="Restore">
        <p class="mb-0">Are you sure you want to restore purchase order <strong id="po-number-restore"></strong>?</p>
        <input type="hidden" id="po-id-restore" name="id" />
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
                var table = $('#table').DataTable({
                    processing: true, serverSide: true, paging: true, scrollX: true,
                    ajax: {
                        url: "{{ route('product.purchase-order.index.data') }}",
                        type: "POST",
                        data: function (d) {
                            d._token = "{{ csrf_token() }}";
                            d.status = $('#selectStatus').val() || "{{ $status }}";
                            d.branch_id = $('#branch_id').val() || '';
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'purchase_number', orderable: true, searchable: true },
                        { data: 'purchase_date', render: function(d) { return d ? moment(d).format("DD MMM YYYY") : '-'; } },
                        { data: 'supplier_name', orderable: false, searchable: true },
                        { data: 'status_badge', orderable: false, searchable: false },
                        { data: 'total_fmt', orderable: false, searchable: false },
                        @if($hasAnyActionPermission){ data: null, orderable: false, searchable: false, render: function(d,t,r) {
                            var html = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                            html += '<li><a class="dropdown-item" href="{{ url("product/purchase-order/detail") }}/'+r.id+'"><i class="ti ti-eye me-2 text-info"></i>Detail</a></li>';
                            if (!r.deleted_at && ((r.status_key || r.status) === 'draft')) {
                                html += '<li><a class="dropdown-item" href="{{ url("product/purchase-order/edit") }}/'+r.id+'"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>';
                                html += '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="'+r.id+'" data-number="'+r.purchase_number+'"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>';
                            }
                            if (r.deleted_at) {
                                html += '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="'+r.id+'" data-number="'+r.purchase_number+'"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>';
                            }
                            return html + '</ul></div>';
                        } }@endif
                    ],
                    dom: '<"card-header flex-column flex-md-row"<"head-label text-center"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row m-0"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } },
                        @if($hasCreatePermission){ text: '<i class="ti ti-plus me-sm-1"></i> Add', className: "btn btn-primary", action: function() { window.location = '{{ route("product.purchase-order.insert.view") }}'; } }@endif
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Purchase Order</h4>');

                // Init Select2 inside filter modal so dropdown appears above modal backdrop
                $('#filterModal').on('shown.bs.modal', function () {
                    $('#branch_id, #selectStatus').each(function () {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({
                                dropdownParent: $('#filterModal'),
                                placeholder: 'All',
                                allowClear: true,
                                width: '100%'
                            });
                        }
                    });
                });

                $("#btnFilter").click(function() {
                    var s = $('#selectStatus').val();
                    var params = [];
                    if (s) params.push('status=' + encodeURIComponent(s));
                    var branchId = $('#branch_id').val();
                    if (branchId) params.push('branch_id=' + encodeURIComponent(branchId));
                    var url = '/product/purchase-order';
                    if (params.length) url += '?' + params.join('&');
                    window.location = url;
                });

                $("#btnResetFilter").click(function() { window.location = '/product/purchase-order'; });
                $('#deleteModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#po-id-deleted').val(b.data('id')); $('#po-number-deleted').text(b.data('number')); });
                $('#restoreModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#po-id-restore').val(b.data('id')); $('#po-number-restore').text(b.data('number')); });
            });
        </script>
    @endpush
</x-app-layout>
