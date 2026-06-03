<x-app-layout>
    @section('title', 'Transaction History | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
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
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th class="text-end">Total</th>
                            <th>Actions</th>
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
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select id="filterStatus" class="select2-modal form-select" data-allow-clear="true">
                <option value="">All</option>
                <option value="draft" @if($status=='draft') selected @endif>Draft</option>
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
        <div class="mb-0">
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

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive/datatables.responsive.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
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
                            order_type: "{{ $orderType }}"
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' },
                        { data: 'sales_number', orderable: true, searchable: true },
                        { data: 'sales_date', render: function(d) { return d ? moment(d).format("DD MMM YYYY") : '-'; } },
                        { data: 'order_type', render: function(d) { return '<span class="badge bg-label-'+(d==='pos'?'info':'primary')+'">'+(d||'-').toUpperCase()+'</span>'; } },
                        { data: 'customer_display', orderable: false },
                        { data: 'method_payment_name', orderable: false },
                        { data: 'status_badge', orderable: false, searchable: false },
                        { data: 'payment_badge', orderable: false, searchable: false },
                        { data: 'total_fmt', orderable: false, searchable: false, className: 'text-end' },
                        { data: null, orderable: false, searchable: false, render: function(d, t, r) {
                            return '<a href="{{ url("transaction/detail") }}/'+r.id+'" class="btn btn-sm btn-outline-primary"><i class="ti ti-eye me-1"></i>Detail</a>';
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
                    if (b) params.push('branch_id=' + b);
                    if (s) params.push('status=' + s);
                    if (ps) params.push('payment_status=' + ps);
                    if (ot) params.push('order_type=' + ot);
                    window.location = '{{ route("transaction.index") }}' + (params.length ? '?' + params.join('&') : '');
                });
                $('#btnResetFilter').click(function() { window.location = '{{ route("transaction.index") }}'; });
            });
        </script>
    @endpush
</x-app-layout>
