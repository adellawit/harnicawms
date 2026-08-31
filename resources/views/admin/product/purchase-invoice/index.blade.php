<x-app-layout>
    @section('title', 'Invoice | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Invoice.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Invoice.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Invoice.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission || $hasCreatePermission;

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
                ['label' => 'Invoice', 'active' => true],
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
                            <th>Kontrabon Number</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>PO Count</th>
                            <th>Total</th>
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
            <label class="form-label">Branch</label>
            <select id="branch_id" class="select2 form-select" data-allow-clear="true">
                <option value="">All Branch</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ $selectedBranchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
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

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('product.purchase-invoice.delete.data')" confirmText="Delete">
        <p class="mb-0">Hapus kontrabon <strong id="invoice-number-deleted"></strong>?</p>
        <input type="hidden" id="invoice-id-deleted" name="id" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('product.purchase-invoice.restore.data')" confirmText="Restore">
        <p class="mb-0">Restore kontrabon <strong id="invoice-number-restore"></strong>?</p>
        <input type="hidden" id="invoice-id-restore" name="id" />
    </x-confirm-modal>

    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('product.purchase-invoice.payment.data') }}" class="modal-content" id="paymentForm">
                @csrf
                <input type="hidden" name="id" id="payment-id" />
                <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-2">Catat pembayaran kontrabon <strong id="payment-number"></strong></p>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><small class="text-muted d-block">Total</small><strong id="payment-total"></strong></div>
                        <div class="col-4"><small class="text-muted d-block">Sudah Bayar</small><strong class="text-success" id="payment-paid"></strong></div>
                        <div class="col-4"><small class="text-muted d-block">Sisa</small><strong class="text-warning" id="payment-balance"></strong></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payment_amount">Nominal Pembayaran <span class="text-danger">*</span></label>
                        <input type="text" id="payment_amount" name="amount" class="form-control number-format" inputmode="decimal" required>
                        <small class="text-muted">Bisa partial — maksimal sisa tagihan.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payment_date">Payment Date <span class="text-danger">*</span></label>
                        <input type="text" id="payment_date" name="payment_date" class="form-control" placeholder="DD/MM/YYYY" value="{{ date('d/m/Y') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payment_reference">Reference No.</label>
                        <input type="text" id="payment_reference" name="payment_reference" class="form-control" placeholder="No. transfer / bukti bayar">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-select select2-payment-method" data-placeholder="-- Pilih metode --">
                            <option value=""></option>
                            @foreach($paymentMethods ?? [] as $method)
                                <option value="{{ $method->name }}">{{ $method->name }}{{ !empty($method->code) ? ' ('.$method->code.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="payment_notes">Notes</label>
                        <textarea id="payment_notes" name="payment_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Save Payment</button>
                </div>
            </form>
        </div>
    </div>

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush
    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive/datatables.responsive.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                var paymentAmountCleave = null;
                var paymentMaxBalance = 0;

                function parseNum(val) {
                    return parseFloat(String(val || 0).replace(/\./g, '').replace(',', '.')) || 0;
                }

                function initPaymentAmountCleave(rawValue) {
                    var el = document.getElementById('payment_amount');
                    if (!el) return;
                    if (paymentAmountCleave) {
                        paymentAmountCleave.destroy();
                        paymentAmountCleave = null;
                        $(el).removeData('cleave');
                    }
                    paymentAmountCleave = new Cleave(el, {
                        numeral: true,
                        numeralThousandsGroupStyle: 'thousand',
                        numeralDecimalMark: ',',
                        delimiter: '.',
                        numeralDecimalScale: 2,
                    });
                    paymentAmountCleave.setRawValue(String(rawValue || 0));
                    $(el).data('cleave', paymentAmountCleave);
                }

                function getPaymentAmount() {
                    if (paymentAmountCleave) {
                        return parseFloat(paymentAmountCleave.getRawValue()) || 0;
                    }
                    return parseNum($('#payment_amount').val());
                }

                var table = $('#table').DataTable({
                    processing: true, serverSide: true, paging: true, scrollX: true,
                    ajax: {
                        url: "{{ route('product.purchase-invoice.index.data') }}",
                        type: "POST",
                        data: function (d) {
                            d._token = "{{ csrf_token() }}";
                            d.status = $('#selectStatus').val() || "{{ $status }}";
                            d.branch_id = $('#branch_id').val() || '';
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'kontrabon_number', orderable: true, searchable: true },
                        { data: 'kontrabon_date', render: function(d) { return d ? moment(d).format('DD MMM YYYY') : '-'; } },
                        { data: 'supplier_name', orderable: false, searchable: true },
                        { data: 'po_count', orderable: false, searchable: false, className: 'text-center' },
                        { data: 'total_fmt', orderable: false, searchable: false },
                        { data: 'status_badge', orderable: false, searchable: false },
                        @if($hasAnyActionPermission){ data: null, orderable: false, searchable: false, render: function(d,t,r) {
                            var html = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                            html += '<li><a class="dropdown-item" href="{{ url("product/purchase-invoice/detail") }}/'+r.id+'"><i class="ti ti-eye me-2 text-info"></i>Detail</a></li>';
                            if (!r.deleted_at && r.can_edit) {
                                html += '<li><a class="dropdown-item" href="{{ url("product/purchase-invoice/edit") }}/'+r.id+'"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>';
                                html += '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="'+r.id+'" data-number="'+r.kontrabon_number+'"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>';
                            }
                            if (!r.deleted_at && r.can_pay) {
                                html += '<li><button type="button" class="dropdown-item btn-open-payment" data-bs-toggle="modal" data-bs-target="#paymentModal" data-id="'+r.id+'" data-number="'+r.kontrabon_number+'" data-total="'+(r.total_fmt || '')+'" data-paid="'+(r.paid_fmt || '')+'" data-balance="'+(r.balance_fmt || '')+'" data-balance-amount="'+(r.balance_amount || 0)+'"><i class="ti ti-cash me-2 text-success"></i>Payment</button></li>';
                            }
                            if (r.deleted_at) {
                                html += '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="'+r.id+'" data-number="'+r.kontrabon_number+'"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>';
                            }
                            return html + '</ul></div>';
                        } }@endif
                    ],
                    dom: '<"card-header flex-column flex-md-row"<"head-label text-center"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row m-0"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                    buttons: [
                        { text: '<i class="ti ti-filter me-sm-1"></i> Filter', className: "btn {{ $isFilter ? 'btn-warning' : 'btn-primary' }}", action: function() { $("#filterModal").modal("show"); } },
                        @if($hasCreatePermission){ text: '<i class="ti ti-plus me-sm-1"></i> Create Invoice', className: "btn btn-primary", action: function() { window.location = '{{ route("product.purchase-invoice.insert.view") }}'; } }@endif
                    ]
                });
                $("div.head-label").html('<h4 class="card-title mb-0">Invoice</h4>');

                $('#filterModal').on('shown.bs.modal', function () {
                    $('#branch_id, #selectStatus').each(function () {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({ dropdownParent: $('#filterModal'), placeholder: 'All', allowClear: true, width: '100%' });
                        }
                    });
                });

                $("#btnFilter").click(function() {
                    var params = [];
                    var s = $('#selectStatus').val();
                    if (s) params.push('status=' + encodeURIComponent(s));
                    var branchId = $('#branch_id').val();
                    if (branchId) params.push('branch_id=' + encodeURIComponent(branchId));
                    var url = '/product/purchase-invoice';
                    if (params.length) url += '?' + params.join('&');
                    window.location = url;
                });
                $("#btnResetFilter").click(function() { window.location = '/product/purchase-invoice'; });
                $('#deleteModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#invoice-id-deleted').val(b.data('id')); $('#invoice-number-deleted').text(b.data('number')); });
                $('#restoreModal').on('show.bs.modal', function(e) { var b=$(e.relatedTarget); $('#invoice-id-restore').val(b.data('id')); $('#invoice-number-restore').text(b.data('number')); });

                var paymentDatePicker = null;
                function initPaymentMethodSelect() {
                    var $el = $('#payment_method');
                    if (!$el.length || $el.hasClass('select2-hidden-accessible')) {
                        return;
                    }
                    $el.select2({
                        placeholder: $el.data('placeholder') || '-- Pilih metode --',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#paymentModal')
                    });
                }
                $('#paymentModal').on('shown.bs.modal', initPaymentMethodSelect);
                $('#paymentModal').on('show.bs.modal', function(e) {
                    var btn = $(e.relatedTarget);
                    var balance = parseFloat(btn.data('balance-amount') || 0);
                    paymentMaxBalance = balance;
                    $('#payment-id').val(btn.data('id'));
                    $('#payment-number').text(btn.data('number') || '-');
                    $('#payment-total').text(btn.data('total') || '-');
                    $('#payment-paid').text(btn.data('paid') || '0');
                    $('#payment-balance').text(btn.data('balance') || '-');
                    initPaymentAmountCleave(balance > 0 ? balance : 0);
                    $('#payment_reference').val('');
                    $('#payment_method').val(null).trigger('change');
                    $('#payment_notes').val('');

                    if (!paymentDatePicker) {
                        paymentDatePicker = flatpickr('#payment_date', {
                            dateFormat: 'd/m/Y',
                            disableMobile: true,
                            allowInput: true,
                            defaultDate: '{{ date('d/m/Y') }}',
                        });
                    } else {
                        paymentDatePicker.setDate('{{ date('d/m/Y') }}', true);
                    }
                });

                $('#paymentForm').on('submit', function(e) {
                    if (paymentDatePicker && paymentDatePicker.selectedDates.length) {
                        $('#payment_date').val(paymentDatePicker.formatDate(paymentDatePicker.selectedDates[0], 'd/m/Y'));
                    }
                    var amount = getPaymentAmount();
                    if (amount <= 0) {
                        e.preventDefault();
                        alert('Nominal pembayaran harus lebih dari 0.');
                        return;
                    }
                    if (paymentMaxBalance > 0 && amount > paymentMaxBalance + 0.000001) {
                        e.preventDefault();
                        alert('Nominal melebihi sisa tagihan.');
                        return;
                    }
                    $('#payment_amount').val(amount);
                });
            });
        </script>
    @endpush
</x-app-layout>
