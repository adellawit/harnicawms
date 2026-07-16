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

    @push('page-css')
        <style>
            .po-progress-bar { height: 6px; min-width: 70px; }
            .po-progress-cell { min-width: 110px; }
            .po-tree-toggle {
                cursor: pointer;
                transition: transform 0.2s ease;
                display: inline-block;
                color: var(--bs-primary);
            }
            .po-tree-toggle.rotated { transform: rotate(90deg); }
            .po-tree-spacer { display: inline-block; width: 1.25rem; }
            tr.po-tree-expanded > td { border-bottom: 0; }
            .po-child-panel {
                background: rgba(var(--bs-primary-rgb), 0.04);
                border-left: 3px solid rgba(var(--bs-primary-rgb), 0.35);
                padding: 0.75rem 1rem 0.75rem 2rem;
            }
            .po-child-table th,
            .po-child-table td { vertical-align: middle; }
            .po-child-indent { padding-left: 0.5rem !important; white-space: nowrap; }
        </style>
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
                            <th style="width:36px;"></th>
                            <th>Purchase Number</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Progress (%)</th>
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

    <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Print Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Atur tampilan dokumen sebelum mencetak <strong id="po-number-print"></strong>.</p>
                    <input type="hidden" id="po-id-print" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="showPricesToggle" checked>
                        <label class="form-check-label" for="showPricesToggle">Tampilkan kolom harga</label>
                    </div>
                    <small class="text-muted d-block mt-2">Jika dimatikan, kolom harga dan ringkasan total tidak ditampilkan di PDF.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnPrintPdf">
                        <i class="ti ti-printer me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('product.purchase-order.update-status.data') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Update Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Ubah status purchase order <strong id="po-number-status"></strong></p>
                        <input type="hidden" id="po-id-status" name="id" />
                        <div class="mb-0">
                            <label class="form-label" for="po-status-select">Status <span class="text-danger">*</span></label>
                            <select id="po-status-select" name="status" class="form-select" required>
                                @foreach($poStatuses as $statusOption)
                                    <option value="{{ $statusOption->key }}">{{ $statusOption->value ?? $statusOption->key }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-2">Alur: Draft → Process → Receiving → Payment. Status Received otomatis saat penerimaan penuh.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
                var childrenUrl = "{{ url('product/purchase-order/children') }}/";

                function flagOn(v) {
                    return v === true || v === 1 || v === '1' || v === 'true';
                }

                function buildActionsHtml(r) {
                    var html = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                    html += '<li><a class="dropdown-item" href="{{ url("product/purchase-order/detail") }}/'+r.id+'"><i class="ti ti-eye me-2 text-info"></i>Detail</a></li>';
                    if (!r.deleted_at) {
                        html += '<li><button type="button" class="dropdown-item btn-open-print" data-bs-toggle="modal" data-bs-target="#printModal" data-id="'+r.id+'" data-number="'+r.purchase_number+'"><i class="ti ti-printer me-2 text-secondary"></i>Print</button></li>';
                    }
                    // Create Sub-PO: hanya CPO yang sudah Process (bukan Draft).
                    if (!r.deleted_at && flagOn(r.can_create_sub)) {
                        html += '<li><a class="dropdown-item" href="{{ route("product.purchase-order.insert.view") }}?po_kind=sub&parent_id='+r.id+'"><i class="ti ti-git-branch me-2 text-info"></i>Create Sub-PO</a></li>';
                    }
                    if (!r.deleted_at && flagOn(r.can_receive)) {
                        html += '<li><a class="dropdown-item" href="{{ url("product/purchase-order/receive") }}/'+r.id+'"><i class="ti ti-package me-2 text-success"></i>Receive</a></li>';
                    }
                    if (!r.deleted_at && ((r.status_key || r.status) === 'draft') && (r.po_kind || 'standalone') !== 'master') {
                        html += '<li><a class="dropdown-item" href="{{ url("product/purchase-order/edit") }}/'+r.id+'"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>';
                        html += '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="'+r.id+'" data-number="'+r.purchase_number+'"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>';
                    }
                    if (!r.deleted_at && flagOn(r.can_update_status)) {
                        html += '<li><button type="button" class="dropdown-item btn-open-status" data-bs-toggle="modal" data-bs-target="#statusModal" data-id="'+r.id+'" data-number="'+r.purchase_number+'" data-status="'+(r.status_key || r.status)+'"><i class="ti ti-refresh me-2 text-primary"></i>Update Status</button></li>';
                    }
                    if (r.deleted_at) {
                        html += '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="'+r.id+'" data-number="'+r.purchase_number+'"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>';
                    }
                    return html + '</ul></div>';
                }

                function formatChildPanel(children) {
                    if (!children || !children.length) {
                        return '<div class="po-child-panel text-muted">Belum ada Release Order.</div>';
                    }

                    var html = '<div class="po-child-panel"><div class="table-responsive"><table class="table table-sm table-bordered mb-0 po-child-table">';
                    html += '<thead class="table-light"><tr><th>Purchase Number</th><th>Date</th><th>Supplier</th><th>Type</th><th>Status</th><th>Progress (%)</th><th>Total</th>';
                    @if($hasAnyActionPermission)
                    html += '<th>Actions</th>';
                    @endif
                    html += '</tr></thead><tbody>';

                    children.forEach(function(r) {
                        html += '<tr>';
                        html += '<td class="po-child-indent"><span class="text-muted me-1">└</span><strong>' + (r.purchase_number || '-') + '</strong></td>';
                        html += '<td>' + (r.purchase_date ? moment(r.purchase_date).format('DD MMM YYYY') : '-') + '</td>';
                        html += '<td>' + (r.supplier_name || '-') + '</td>';
                        html += '<td>' + (r.po_kind_badge || '-') + '</td>';
                        html += '<td>' + (r.status_badge || '-') + '</td>';
                        html += '<td>' + (r.progress_display || '-') + '</td>';
                        html += '<td>' + (r.total_fmt || '-') + '</td>';
                        @if($hasAnyActionPermission)
                        html += '<td>' + buildActionsHtml(r) + '</td>';
                        @endif
                        html += '</tr>';
                    });

                    html += '</tbody></table></div></div>';
                    return html;
                }

                function getListFilters() {
                    return {
                        status: $('#selectStatus').val() || "{{ $status }}",
                        branch_id: $('#branch_id').val() || ''
                    };
                }

                var table = $('#table').DataTable({
                    processing: true, serverSide: true, paging: true, scrollX: true,
                    ajax: {
                        url: "{{ route('product.purchase-order.index.data') }}",
                        type: "POST",
                        data: function (d) {
                            d._token = "{{ csrf_token() }}";
                            d.status = getListFilters().status;
                            d.branch_id = getListFilters().branch_id;
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'tree_control', orderable: false, searchable: false, className: 'text-center' },
                        { data: 'purchase_number_display', orderable: true, searchable: true, render: function(d, t, r) {
                            return d || r.purchase_number || '-';
                        }},
                        { data: 'purchase_date', render: function(d) { return d ? moment(d).format("DD MMM YYYY") : '-'; } },
                        { data: 'supplier_name', orderable: false, searchable: true },
                        { data: 'po_kind_badge', orderable: false, searchable: false },
                        { data: 'status_badge', orderable: false, searchable: false },
                        { data: 'progress_display', orderable: false, searchable: false },
                        { data: 'total_fmt', orderable: false, searchable: false },
                        @if($hasAnyActionPermission){ data: null, orderable: false, searchable: false, render: function(d,t,r) {
                            return buildActionsHtml(r);
                        } }@endif
                    ],
                    dom: '<"card-header flex-column flex-md-row"<"head-label text-center"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row m-0"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } },
                        @if($hasCreatePermission){ text: '<i class="ti ti-plus me-sm-1"></i> Add', className: "btn btn-primary", action: function() { window.location = '{{ route("product.purchase-order.insert.view") }}'; } }@endif
                    ],
                    drawCallback: function() {
                        $('.po-tree-toggle').removeClass('rotated');
                        $('tr.po-tree-expanded').removeClass('po-tree-expanded');
                    }
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Purchase Order</h4>');

                $('#table tbody').on('click', '.po-tree-toggle', function(e) {
                    e.stopPropagation();
                    var $icon = $(this);
                    var tr = $icon.closest('tr');
                    var row = table.row(tr);
                    var parentId = $icon.data('id');

                    if (row.child.isShown()) {
                        row.child.hide();
                        $icon.removeClass('rotated');
                        tr.removeClass('po-tree-expanded');
                        return;
                    }

                    var showChildren = function(children) {
                        row.child(formatChildPanel(children)).show();
                        $icon.addClass('rotated');
                        tr.addClass('po-tree-expanded');
                    };

                    var cached = $icon.data('children');
                    if (cached) {
                        showChildren(cached);
                        return;
                    }

                    $icon.addClass('opacity-50');
                    $.get(childrenUrl + parentId, getListFilters(), function(res) {
                        $icon.removeClass('opacity-50');
                        $icon.data('children', res.data || []);
                        showChildren(res.data || []);
                    }).fail(function() {
                        $icon.removeClass('opacity-50');
                        alert('Gagal memuat Release Order.');
                    });
                });

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
                $('#statusModal').on('show.bs.modal', function(e) {
                    var btn = $(e.relatedTarget);
                    $('#po-id-status').val(btn.data('id'));
                    $('#po-number-status').text(btn.data('number'));
                    $('#po-status-select').val(btn.data('status') || 'draft');
                });
                $('#printModal').on('show.bs.modal', function(e) {
                    var btn = $(e.relatedTarget);
                    $('#po-id-print').val(btn.data('id'));
                    $('#po-number-print').text(btn.data('number'));
                    $('#showPricesToggle').prop('checked', true);
                });
                $('#btnPrintPdf').on('click', function() {
                    var poId = $('#po-id-print').val();
                    if (!poId) return;
                    var showPrices = $('#showPricesToggle').is(':checked') ? 1 : 0;
                    var url = '{{ url("product/purchase-order/detail") }}/' + poId + '/pdf?show_prices=' + showPrices;
                    window.open(url, '_blank');
                    bootstrap.Modal.getInstance(document.getElementById('printModal'))?.hide();
                });
            });
        </script>
    @endpush
</x-app-layout>
