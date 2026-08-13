<x-app-layout>

    @section('title', 'Product | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $isSuperAdmin = auth('web')->user()?->is_super_admin;
            $hasReadPermission = $isSuperAdmin || session('permissions.Product.is_read', false) == 1;
            $hasUpdatePermission = $isSuperAdmin || session('permissions.Product.is_update', false) == 1;
            $hasDeletePermission = $isSuperAdmin || session('permissions.Product.is_delete', false) == 1;
            $hasCreatePermission = $isSuperAdmin || session('permissions.Product.is_create', false) == 1;
            $hasAnyActionPermission = $hasReadPermission || $hasUpdatePermission || $hasDeletePermission;

            $branches = \App\Models\BusinessUnit::where('is_active', true)
                ->where('type_code', 'BRANCH')
                ->orderBy('name')
                ->get(['id', 'name']);
            $defaultBranch = auth('web')->user()->current_business_unit_id;
            $filterBranchId = request('branch_id', $defaultBranch);
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Product', 'active' => true]
            ]"
        />

        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        @if (session('import_errors'))
            <x-alert type="danger">
                <strong>Import gagal, perbaiki data berikut:</strong>
                <ul class="mb-0 mt-1">
                    @foreach(session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <div class="card">
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered table-hover" id="table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th style="width: 120px;">SKU</th>
                            <th style="width: 100px;">Code</th>
                            <th style="width: 200px;">Product</th>
                            <th style="width: 250px;">Variant</th>
                            <th style="width: 120px;">Product Type</th>
                            <th style="width: 140px;">Item Type</th>
                            <th style="width: 140px;">Nature</th>
                            <th style="width: 140px;">Procurement</th>
                            <th style="width: 160px;">Lifecycle</th>
                            <th style="width: 120px;">Category</th>
                            <th style="width: 150px;">Created At</th>
                            <th style="width: 80px;">Status</th>
                            @if($hasAnyActionPermission)<th style="width: 60px;">Actions</th>@endif
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        <div class="modal fade" id="filterModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Filter</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Branch</label>
                            <select id="filterBranch" class="select2-modal form-select" data-allow-clear="true">
                                <option value="">All Branch</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @if($filterBranchId === $b->id) selected @endif>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SKU</label>
                            <input type="text" id="filterSku" class="form-control" placeholder="SKU" value="{{ request('sku', '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" id="filterProduct" class="form-control" placeholder="Name / Code" value="{{ request('product', '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Type</label>
                            <select id="filterNature" class="select2-modal form-select" data-allow-clear="true">
                                <option value="">All</option>
                                @forelse($natures as $id => $name)
                                    <option value="{{ $id }}" {{ request('nature_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @empty
                                @endforelse
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select id="filterCategory" class="select2-modal form-select" data-allow-clear="true">
                                <option value="">All</option>
                                @forelse($categories as $id => $name)
                                    <option value="{{ $id }}" {{ request('category_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @empty
                                @endforelse
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Item Type</label>
                            <select id="filterItemType" class="select2-modal form-select" data-allow-clear="true">
                                <option value="">All</option>
                                @foreach($itemTypes as $id => $name)
                                    <option value="{{ $id }}" {{ request('item_type_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nature</label>
                            <select id="filterProductNature" class="select2-modal form-select" data-allow-clear="true">
                                <option value="">All</option>
                                @foreach($productNatures as $id => $name)
                                    <option value="{{ $id }}" {{ request('product_nature_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Procurement</label>
                            <select id="filterProcurementType" class="select2-modal form-select" data-allow-clear="true">
                                <option value="">All</option>
                                @foreach($procurementTypes as $id => $name)
                                    <option value="{{ $id }}" {{ request('procurement_type_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock</label>
                                <select id="filterStockItem" class="select2-modal form-select" data-allow-clear="true">
                                    <option value="">All</option>
                                    <option value="1" {{ request('is_stock_item') === '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ request('is_stock_item') === '0' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sales</label>
                                <select id="filterSaleItem" class="select2-modal form-select" data-allow-clear="true">
                                    <option value="">All</option>
                                    <option value="1" {{ request('is_sale_item') === '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ request('is_sale_item') === '0' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Purchase</label>
                                <select id="filterPurchaseItem" class="select2-modal form-select" data-allow-clear="true">
                                    <option value="">All</option>
                                    <option value="1" {{ request('is_purchase_item') === '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ request('is_purchase_item') === '0' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="filterStatus" class="select2-modal form-select" data-allow-clear="true">
                                <option value="">All</option>
                                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="deleted" {{ request('status') == 'deleted' ? 'selected' : '' }}>Deleted</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" id="btnResetFilter">Reset</button>
                        <button type="button" class="btn btn-primary" id="btnFilter" data-bs-dismiss="modal">Filter</button>
                    </div>
                </div>
            </div>
        </div>

        <x-confirm-modal id="deleteModal" title="Delete" :action="route('product.delete.data')" confirm-text="Submit">
            <input type="hidden" id="product-id-deleted" name="product_id_deleted" />
            <p class="mb-0">Are you sure you want to delete <strong id="product-name-deleted"></strong>?</p>
        </x-confirm-modal>

        <x-confirm-modal id="restoreModal" title="Restore" :action="route('product.restore.data')" confirm-text="Submit">
            <input type="hidden" id="product-id-restore" name="product_id_restored" />
            <p class="mb-0">Are you sure you want to restore <strong id="product-name-restore"></strong>?</p>
        </x-confirm-modal>

        @if($hasCreatePermission)
        <div class="modal fade" id="importModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('product.import.data') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Import Product</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">File Excel <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required />
                                <small class="text-muted">Format: .xlsx, .xls, .csv (maks 5MB)</small>
                            </div>
                            <div class="alert alert-info py-2 mb-0">
                                <small>
                                    <i class="ti ti-info-circle me-1"></i>
                                    Download template terlebih dahulu agar format sesuai.
                                    <a href="{{ route('product.import.template') }}" class="fw-bold">
                                        <i class="ti ti-download"></i> Download Template
                                    </a>
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success">
                                <i class="ti ti-upload me-1"></i> Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        @endpush

        @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <style>
            .variant-list {
                font-size: 0.85rem;
                line-height: 1.5;
            }
            .variant-list > div {
                white-space: normal;
                word-break: break-word;
            }
            #table_wrapper .dataTables_filter label {
                display: inline-flex;
                align-items: center;
            }
        </style>
        <script>
            $(document).ready(function() {
                $('#table').DataTable({
                    processing: true,
                    serverSide: true,
                    paging: true,
                    scrollX: true,
                    autoWidth: false,
                    ajax: {
                        url: "{{ route('product.index.data') }}",
                        type: "POST",
                        data: function(d) {
                            d._token = "{{ csrf_token() }}";
                            d.status = "{{ $status }}";
                            d.branch_id = "{{ $branchId ?? $defaultBranch }}";
                            d.sku = "{{ request('sku', '') }}";
                            d.product = "{{ request('product', '') }}";
                            d.nature_id = "{{ request('nature_id', '') }}";
                            d.category_id = "{{ request('category_id', '') }}";
                            d.item_type_id = "{{ request('item_type_id', '') }}";
                            d.product_nature_id = "{{ request('product_nature_id', '') }}";
                            d.procurement_type_id = "{{ request('procurement_type_id', '') }}";
                            d.is_stock_item = "{{ request('is_stock_item', '') }}";
                            d.is_sale_item = "{{ request('is_sale_item', '') }}";
                            d.is_purchase_item = "{{ request('is_purchase_item', '') }}";
                        },
                        error: function(xhr) {
                            var msg = 'Gagal memuat data tabel';
                            if (xhr.status === 419) {
                                msg = 'Sesi kedaluwarsa. Silakan refresh halaman dan login ulang.';
                            } else if (xhr.responseJSON && xhr.responseJSON.error) {
                                msg = xhr.responseJSON.error;
                            } else if (xhr.responseText && xhr.responseText.indexOf('<!DOCTYPE') === 0) {
                                msg = 'Server mengembalikan HTML, bukan JSON (kemungkinan redirect login atau error server).';
                            }
                            console.error('DataTables AJAX error:', xhr.status, xhr.responseText);
                            alert(msg);
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false, width: '50px' },
                        { data: 'sku', width: '120px' },
                        { data: 'code', width: '100px' },
                        { data: 'name', width: '200px' },
                        { data: 'variants_list', orderable: false, searchable: false, width: '250px' },
                        { data: 'nature_name', orderable: false, searchable: false, width: '120px' },
                        { data: 'item_type_name', orderable: false, searchable: false, width: '140px' },
                        { data: 'product_nature_name', orderable: false, searchable: false, width: '140px' },
                        { data: 'procurement_type_name', orderable: false, searchable: false, width: '140px' },
                        { data: 'lifecycle_flags', orderable: false, searchable: false, width: '160px' },
                        { data: 'category_name', orderable: false, searchable: false, width: '120px' },
                        { data: 'created_at', width: '150px', render: function(d) {
                            return d ? moment(d).format("DD MMM YYYY - HH:mm") : '-';
                        } },
                        { data: 'deleted_at', width: '80px', render: function(d) {
                            return d ? '<span class="badge bg-label-danger">Deleted</span>' : '<span class="badge bg-label-success">Active</span>';
                        } },
                        @if($hasAnyActionPermission){ data: null, orderable: false, searchable: false, width: '60px', render: function(d,t,r) {
                            var html = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                            if (@json($hasReadPermission) && r.procurement_type_key === 'purchase') html += '<li><a class="dropdown-item" href="{{ url("product/items") }}/'+r.id+'/print-barcode"><i class="ti ti-printer me-2 text-info"></i>Print QR</a></li>';
                            if (@json($hasReadPermission)) html += '<li><a class="dropdown-item" href="{{ url("product/items/variants") }}/'+r.id+'"><i class="ti ti-list me-2 text-primary"></i>Manage Variants</a></li>';
                            if (@json($hasUpdatePermission)) html += '<li><a class="dropdown-item" href="{{ url("product/items/edit") }}/'+r.id+'"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>';
                            if (@json($hasDeletePermission)) html += r.deleted_at ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="'+r.id+'" data-name="'+r.name+'"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>' : '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="'+r.id+'" data-name="'+r.name+'"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>';
                            return html + '</ul></div>';
                        } }@endif
                    ],
                    dom: '<"card-header flex-column flex-md-row"<"head-label"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row m-0"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } },
                        { text: '<i class="ti ti-download me-sm-1"></i> Export', className: "btn btn-info", action: function() { window.location = '{{ route("product.export.data") }}'; } },
                        @if($hasCreatePermission){ text: '<i class="ti ti-upload me-sm-1"></i> Import', className: "btn btn-success", action: function() { $("#importModal").modal("show"); } },@endif
                        @if($hasCreatePermission){ text: '<i class="ti ti-plus me-sm-1"></i> Add', className: "btn btn-primary", action: function() { window.location = '{{ route("product.insert.view") }}'; } }@endif
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Product</h4>');
                
                function buildFilterUrl() {
                    var params = [];
                    if ($('#filterSku').val()) params.push('sku=' + encodeURIComponent($('#filterSku').val()));
                    if ($('#filterProduct').val()) params.push('product=' + encodeURIComponent($('#filterProduct').val()));
                    if ($('#filterNature').val()) params.push('nature_id=' + $('#filterNature').val());
                    if ($('#filterCategory').val()) params.push('category_id=' + $('#filterCategory').val());
                    if ($('#filterItemType').val()) params.push('item_type_id=' + $('#filterItemType').val());
                    if ($('#filterProductNature').val()) params.push('product_nature_id=' + $('#filterProductNature').val());
                    if ($('#filterProcurementType').val()) params.push('procurement_type_id=' + $('#filterProcurementType').val());
                    if ($('#filterStockItem').val() !== '') params.push('is_stock_item=' + $('#filterStockItem').val());
                    if ($('#filterSaleItem').val() !== '') params.push('is_sale_item=' + $('#filterSaleItem').val());
                    if ($('#filterPurchaseItem').val() !== '') params.push('is_purchase_item=' + $('#filterPurchaseItem').val());
                    if ($('#filterStatus').val()) params.push('status=' + $('#filterStatus').val());
                    if ($('#filterBranch').val()) params.push('branch_id=' + $('#filterBranch').val());
                    return '/product/items' + (params.length > 0 ? '?' + params.join('&') : '');
                }
                
                // Initialize Select2 for filter modal (same pattern as /product/stock)
                $('#filterModal').on('shown.bs.modal', function () {
                    $('#filterBranch, #filterNature, #filterCategory, #filterItemType, #filterProductNature, #filterProcurementType, #filterStockItem, #filterSaleItem, #filterPurchaseItem, #filterStatus').each(function () {
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
                    window.location = buildFilterUrl();
                });
                
                $("#btnResetFilter").click(function() { 
                    window.location = '/product/items';
                });
                $('#deleteModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#product-id-deleted').val(b.data('id')); $('#product-name-deleted').text(b.data('name')); });
                $('#restoreModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#product-id-restore').val(b.data('id')); $('#product-name-restore').text(b.data('name')); });
            });
        </script>
    @endpush

</x-app-layout>
