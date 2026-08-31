<x-app-layout>
    @section('title', 'Transaction History | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
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
                ['label' => 'Transaction'],
                ['label' => 'History', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>
        @endif

        <div class="card">
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered" id="table">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Sales Number</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Gudang</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th class="text-end">Ongkir</th>
                            <th class="text-end">Total</th>
                            <th class="text-nowrap">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <div class="mb-3">
            <label class="form-label">Branch</label>
            <select id="filterBranch" class="select2-modal form-select" data-allow-clear="true">
                <option value="">All Branch</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @if($filterBranchId === $b->id) selected @endif>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Date From</label>
                <input type="text" id="filterDateFrom" class="form-control flatpickr-date"
                       value="{{ format_date_id($dateFrom ?? '') }}" placeholder="DD/MM/YYYY" autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label">Date To</label>
                <input type="text" id="filterDateTo" class="form-control flatpickr-date"
                       value="{{ format_date_id($dateTo ?? '') }}" placeholder="DD/MM/YYYY" autocomplete="off">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select id="filterStatus" class="select2-modal form-select" data-allow-clear="true">
                <option value="">All</option>
                <option value="draft" @if($status=='draft') selected @endif>Draft</option>
                <option value="verification" @if($status=='verification') selected @endif>Verification</option>
                <option value="completed" @if($status=='completed') selected @endif>Completed</option>
                <option value="cancelled" @if($status=='cancelled') selected @endif>Cancelled</option>
                <option value="deleted" @if($status=='deleted') selected @endif>Deleted</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Payment Status</label>
            <select id="filterPaymentStatus" class="select2-modal form-select" data-allow-clear="true">
                <option value="">All</option>
                <option value="paid" @if($paymentStatus=='paid') selected @endif>Paid</option>
                <option value="unpaid" @if($paymentStatus=='unpaid') selected @endif>Unpaid</option>
                <option value="partial" @if($paymentStatus=='partial') selected @endif>Partial</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Gudang</label>
            <select id="filterWarehouse" class="select2-modal form-select" data-allow-clear="true">
                <option value="">Semua Gudang</option>
                @foreach($warehouses ?? [] as $wh)
                    <option value="{{ $wh->id }}" @if(($warehouseId ?? '') === $wh->id) selected @endif>
                        {{ $wh->name }}@if($wh->code) ({{ $wh->code }})@endif
                    </option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Order Type</label>
            <select id="filterOrderType" class="select2-modal form-select" data-allow-clear="true">
                <option value="">All</option>
                <option value="pos" @if($orderType=='pos') selected @endif>POS</option>
                <option value="standard" @if($orderType=='standard') selected @endif>Standard</option>
            </select>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-label-dark" id="btnResetFilter">Reset</button>
            <button type="button" class="btn btn-primary" id="btnFilter" data-bs-dismiss="modal">Filter</button>
        </x-slot:footer>
    </x-modal>

    <x-modal id="shippingModal" title="Update Nomor Resi">
        <form id="shippingForm" method="POST" action="">
            @csrf
            <div class="mb-0">
                <label class="form-label">Nomor Resi</label>
                <input type="text" name="shipping_tracking_number" id="shippingTrackingNumberInput" class="form-control"
                    required maxlength="100" placeholder="Contoh: JNE1234567890">
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Batal</button>
            <button type="submit" form="shippingForm" class="btn btn-primary">Simpan</button>
        </x-slot:footer>
    </x-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive/datatables.responsive.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @push('page-css')
        <style>
            #table .trx-actions {
                display: inline-flex;
                flex-wrap: nowrap;
                align-items: center;
                gap: 0.35rem;
                white-space: nowrap;
            }
            #table td:last-child {
                white-space: nowrap;
                width: 1%;
            }
        </style>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

                function toIsoDate(value) {
                    if (!value) return '';
                    value = String(value).trim();
                    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
                    var m = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                    if (m) {
                        var d = ('0' + m[1]).slice(-2);
                        var mo = ('0' + m[2]).slice(-2);
                        return m[3] + '-' + mo + '-' + d;
                    }
                    return value;
                }

                $('.flatpickr-date').flatpickr({
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    disableMobile: true
                });

                $('#table').DataTable({
                    processing: true, serverSide: true, paging: true, scrollX: true,
                    ajax: {
                        url: "{{ route('transaction.index.data') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            branch_id: "{{ $branchId }}",
                            status: "{{ $status }}",
                            payment_status: "{{ $paymentStatus }}",
                            order_type: "{{ $orderType }}",
                            date_from: "{{ $dateFrom ?? '' }}",
                            date_to: "{{ $dateTo ?? '' }}",
                            warehouse_id: "{{ $warehouseId ?? '' }}"
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' },
                        { data: 'sales_number', orderable: true, searchable: true },
                        { data: 'sales_date', render: function(d) { return d ? moment(d).format("DD MMM YYYY") : '-'; } },
                        { data: 'order_type', render: function(d) { return '<span class="badge bg-label-'+(d==='pos'?'info':'primary')+'">'+(d||'-').toUpperCase()+'</span>'; } },
                        { data: 'customer_display', orderable: false },
                        { data: 'warehouse_display', orderable: false, searchable: false },
                        { data: 'method_payment_name', orderable: false },
                        { data: 'status_badge', orderable: false, searchable: false },
                        { data: 'payment_badge', orderable: false, searchable: false },
                        { data: 'shipping_fmt', orderable: false, searchable: false, className: 'text-end' },
                        { data: 'total_fmt', orderable: false, searchable: false, className: 'text-end' },
                        { data: null, orderable: false, searchable: false, className: 'text-nowrap', render: function(d, t, r) {
                            var detailUrl = "{{ url('transaction/detail') }}/" + r.id;
                            var printUrl = "{{ url('transaction') }}/" + r.id + "/print-invoice?print=1";
                            var shippingUrl = "{{ url('transaction') }}/" + r.id + "/print-shipping?print=1";
                            var html = '<div class="trx-actions">'
                                + '<a href="' + detailUrl + '" class="btn btn-sm btn-outline-primary" title="Detail"><i class="ti ti-eye me-1"></i>Detail</a>';
                            if (r.status === 'verification' && !r.deleted_at) {
                                var verifyUrl = "{{ url('transaction') }}/" + r.id + "/verify";
                                html += '<a href="' + verifyUrl + '" class="btn btn-sm btn-success" title="Verify"><i class="ti ti-check me-1"></i>Verify</a>';
                            }
                            if ((r.status === 'shipped' || r.status === 'completed') && !r.deleted_at) {
                                html += '<button type="button" class="btn btn-sm btn-info btn-shipping-action" data-bs-toggle="modal" data-bs-target="#shippingModal" data-shipping-id="' + r.id + '" data-shipping-value="' + escapeHtml(r.shipping_tracking_number || '') + '" title="Shipping"><i class="ti ti-truck me-1"></i>Shipping</button>';
                            }
                            html += '<a href="' + printUrl + '" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Invoice"><i class="ti ti-printer me-1"></i>Invoice</a>';
                            if (r.shipping_tracking_number) {
                                html += '<a href="' + shippingUrl + '" target="_blank" class="btn btn-sm btn-outline-info" title="Print Surat Jalan"><i class="ti ti-truck-delivery me-1"></i>Surat Jalan</a>';
                            }
                            html += '</div>';
                            return html;
                        } }
                    ],
                    dom: '<"card-header flex-column flex-md-row"<"head-label text-center"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row m-0"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } }
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Transaction History</h4>');

                $('#filterModal').on('shown.bs.modal', function() {
                    $('.select2-modal').each(function() {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({ dropdownParent: $('#filterModal'), placeholder: 'All', allowClear: true, width: '100%' });
                        }
                    });
                });

                $('#btnFilter').click(function() {
                    var params = [];
                    var b = $('#filterBranch').val();
                    var s = $('#filterStatus').val();
                    var ps = $('#filterPaymentStatus').val();
                    var ot = $('#filterOrderType').val();
                    var wh = $('#filterWarehouse').val();
                    var df = toIsoDate($('#filterDateFrom').val());
                    var dt = toIsoDate($('#filterDateTo').val());
                    if (b) params.push('branch_id=' + encodeURIComponent(b));
                    if (s) params.push('status=' + encodeURIComponent(s));
                    if (ps) params.push('payment_status=' + encodeURIComponent(ps));
                    if (ot) params.push('order_type=' + encodeURIComponent(ot));
                    if (wh) params.push('warehouse_id=' + encodeURIComponent(wh));
                    if (df) params.push('date_from=' + encodeURIComponent(df));
                    if (dt) params.push('date_to=' + encodeURIComponent(dt));
                    window.location = '{{ route("transaction.index") }}' + (params.length ? '?' + params.join('&') : '');
                });
                $('#btnResetFilter').click(function() { window.location = '{{ route("transaction.index") }}'; });

                $(document).on('click', '.btn-shipping-action', function() {
                    var orderId = $(this).data('shipping-id');
                    var current = $(this).data('shipping-value') || '';
                    $('#shippingForm').attr('action', "{{ url('transaction') }}/" + orderId + "/shipping");
                    $('#shippingTrackingNumberInput').val(current);
                });
            });
        </script>
    @endpush
</x-app-layout>
