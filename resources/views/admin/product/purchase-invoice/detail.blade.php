<x-app-layout>
    @section('title', 'Detail Kontrabon | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Invoice.is_update', false) == 1;
            $canEdit = \App\Support\KontrabonStatus::canEdit($kontrabon);
            $canSubmit = \App\Support\KontrabonStatus::canSubmit($kontrabon);
            $canPay = \App\Support\KontrabonStatus::canPay($kontrabon);
            $canCancel = \App\Support\KontrabonStatus::canCancel($kontrabon);
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Invoice', 'url' => route('product.purchase-invoice.index.view')],
                ['label' => 'Detail', 'active' => true],
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

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Kontrabon — {{ $kontrabon->kontrabon_number }}</h5>
                    <span class="badge bg-label-{{ \App\Support\KontrabonStatus::badgeClass($kontrabon->status) }}">{{ $kontrabon->status_label }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if($canEdit && $hasUpdatePermission)
                        <a href="{{ route('product.purchase-invoice.edit.view', $kontrabon->id) }}" class="btn btn-warning btn-sm">
                            <i class="ti ti-pencil me-1"></i>Edit
                        </a>
                    @endif
                    @if($canSubmit && $hasUpdatePermission)
                        <form method="POST" action="{{ route('product.purchase-invoice.submit.data') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="id" value="{{ $kontrabon->id }}">
                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Submit kontrabon ini?')">
                                <i class="ti ti-send me-1"></i>Submit
                            </button>
                        </form>
                    @endif
                    @if($canPay && $hasUpdatePermission)
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
                            <i class="ti ti-cash me-1"></i>Payment
                        </button>
                    @endif
                    @if($canCancel && $hasUpdatePermission)
                        <form method="POST" action="{{ route('product.purchase-invoice.cancel.data') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="id" value="{{ $kontrabon->id }}">
                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Batalkan kontrabon ini?')">
                                <i class="ti ti-x me-1"></i>Cancel
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('product.purchase-invoice.index.view') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Date</small>
                        <strong>{{ $kontrabon->kontrabon_date?->format('d M Y') }}</strong>
                    </div>
                    <div class="col-md-5">
                        <small class="text-muted d-block">Supplier</small>
                        <strong>{{ $kontrabon->supplier_name }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Branch</small>
                        <strong>{{ $kontrabon->branch?->name ?? '-' }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Total Tagihan</small>
                        <strong>{{ format_number((float) $kontrabon->total, 2, true) }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Sudah Dibayar</small>
                        <strong class="text-success">{{ format_number((float) ($kontrabon->paid_amount ?? 0), 2, true) }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Sisa Tagihan</small>
                        <strong class="text-warning">{{ format_number($kontrabon->balance_amount, 2, true) }}</strong>
                    </div>
                    @if($kontrabon->notes)
                    <div class="col-12">
                        <small class="text-muted d-block">Notes</small>
                        <div>{{ $kontrabon->notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Purchase Order Items</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>PO Number</th>
                                <th>No. Faktur Supplier</th>
                                <th>Tgl Faktur</th>
                                <th class="text-end">Total PO</th>
                                <th class="text-end">Nominal Invoice</th>
                                <th>File</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kontrabon->items as $item)
                            <tr>
                                <td>
                                    @if($item->purchaseOrder)
                                        <a href="{{ route('product.purchase-order.detail.view', $item->purchase_order_id) }}">{{ $item->purchaseOrder->purchase_number }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $item->supplier_invoice_number ?: '-' }}</td>
                                <td>{{ $item->supplier_invoice_date?->format('d M Y') ?: '-' }}</td>
                                <td class="text-end">{{ format_number((float) ($item->po_total ?: $item->total), 2, true) }}</td>
                                <td class="text-end fw-semibold">{{ format_number((float) $item->total, 2, true) }}</td>
                                <td>
                                    @if($item->has_attachment)
                                        <a href="{{ $item->attachment_url }}" target="_blank" rel="noopener">
                                            <i class="ti ti-paperclip me-1"></i>{{ $item->attachment_name ?: 'Lihat file' }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Grand Total</th>
                                <th class="text-end">{{ format_number((float) $kontrabon->total, 2, true) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        @if(($kontrabon->paid_amount ?? 0) > 0)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Riwayat Pembayaran</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th class="text-end">Nominal</th>
                                <th>Reference</th>
                                <th>Method</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($kontrabon->payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date?->format('d M Y') }}</td>
                                <td class="text-end">{{ format_number((float) $payment->amount, 2, true) }}</td>
                                <td>{{ $payment->payment_reference ?: '-' }}</td>
                                <td>{{ $payment->payment_method ?: '-' }}</td>
                                <td>{{ $payment->payment_notes ?: '-' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada pembayaran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('product.purchase-invoice.payment.data') }}" class="modal-content" id="detailPaymentForm">
                    @csrf
                    <input type="hidden" name="id" value="{{ $kontrabon->id }}">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-2">Kontrabon <strong>{{ $kontrabon->kontrabon_number }}</strong></p>
                        <div class="row g-2 mb-3">
                            <div class="col-4"><small class="text-muted d-block">Total</small><strong>{{ format_number((float) $kontrabon->total, 2, true) }}</strong></div>
                            <div class="col-4"><small class="text-muted d-block">Sudah Bayar</small><strong class="text-success">{{ format_number((float) ($kontrabon->paid_amount ?? 0), 2, true) }}</strong></div>
                            <div class="col-4"><small class="text-muted d-block">Sisa</small><strong class="text-warning">{{ format_number($kontrabon->balance_amount, 2, true) }}</strong></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="payment_amount">Nominal Pembayaran <span class="text-danger">*</span></label>
                            <input type="text" name="amount" id="payment_amount" class="form-control number-format" inputmode="decimal"
                                value="{{ format_number((float) $kontrabon->balance_amount, 2, true) }}"
                                data-max="{{ (float) $kontrabon->balance_amount }}" required>
                            <small class="text-muted">Bisa partial — maksimal sisa tagihan.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="text" name="payment_date" id="payment_date" class="form-control" placeholder="DD/MM/YYYY" value="{{ date('d/m/Y') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reference No.</label>
                            <input type="text" name="payment_reference" class="form-control" placeholder="No. transfer / bukti bayar">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="">-- Pilih --</option>
                                <option value="Transfer">Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Giro">Giro</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Notes</label>
                            <textarea name="payment_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Save Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush
    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            (function () {
                var paymentMaxBalance = parseFloat(@json((float) $kontrabon->balance_amount)) || 0;
                var paymentDatePicker = flatpickr('#payment_date', { dateFormat: 'd/m/Y', disableMobile: true, allowInput: true });

                function parseNum(val) {
                    return parseFloat(String(val || 0).replace(/\./g, '').replace(',', '.')) || 0;
                }

                var amountEl = document.getElementById('payment_amount');
                var paymentAmountCleave = new Cleave(amountEl, {
                    numeral: true,
                    numeralThousandsGroupStyle: 'thousand',
                    numeralDecimalMark: ',',
                    delimiter: '.',
                    numeralDecimalScale: 2,
                });
                paymentAmountCleave.setRawValue(String(paymentMaxBalance));

                $('#detailPaymentForm').on('submit', function (e) {
                    if (paymentDatePicker && paymentDatePicker.selectedDates.length) {
                        $('#payment_date').val(paymentDatePicker.formatDate(paymentDatePicker.selectedDates[0], 'd/m/Y'));
                    }
                    var amount = parseFloat(paymentAmountCleave.getRawValue()) || parseNum($('#payment_amount').val());
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
            })();
        </script>
    @endpush
</x-app-layout>
