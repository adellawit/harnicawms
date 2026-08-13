<x-app-layout>
    @section('title', 'Transaction Detail | ')
    @push('page-css')
        <style>
            .detail-info td { padding: 0.4rem 0.5rem; }
            .detail-info td:first-child { color: #9aa4b8; width: 180px; white-space: nowrap; }
            .detail-info td:last-child { font-weight: 600; }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Transaction', 'url' => route('transaction.index')],
                ['label' => 'Detail', 'active' => true],
            ]"
        />

        @php
            $statusColors = ['completed' => 'success', 'draft' => 'secondary', 'cancelled' => 'danger', 'pending' => 'warning'];
            $payStatusColors = ['paid' => 'success', 'unpaid' => 'danger', 'partial' => 'warning'];
        @endphp

        {{-- Order Info --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $order->sales_number }}</h5>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-label-{{ $statusColors[$order->status] ?? 'info' }}">{{ ucfirst($order->status) }}</span>
                    <span class="badge bg-label-{{ $payStatusColors[$order->payment_status] ?? 'secondary' }}">{{ ucfirst($order->payment_status) }}</span>
                    <a href="{{ route('transaction.print-invoice', $order->id) }}?print=1" target="_blank" class="btn btn-outline-secondary btn-sm ms-2">
                        <i class="ti ti-printer me-1"></i>Print Invoice
                    </a>
                    <a href="{{ route('transaction.print-shipping', $order->id) }}?print=1" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="ti ti-truck-delivery me-1"></i>Print Surat Jalan
                    </a>
                    <a href="{{ route('transaction.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless detail-info mb-0">
                            <tr>
                                <td>Order Number</td>
                                <td>{{ $order->sales_number }}</td>
                            </tr>
                            <tr>
                                <td>Date</td>
                                <td>{{ $order->sales_date?->format('d M Y') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td>Order Type</td>
                                <td><span class="badge bg-label-{{ $order->order_type === 'pos' ? 'info' : 'primary' }}">{{ strtoupper($order->order_type) }}</span></td>
                            </tr>
                            <tr>
                                <td>Customer</td>
                                <td>{{ $order->customer_name ?: 'Walk-in Customer' }}</td>
                            </tr>
                            <tr>
                                <td>Branch</td>
                                <td>{{ $order->branch?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td>Price List</td>
                                <td>{{ $order->priceList ? ($order->priceList->name . ' (' . $order->priceList->code . ')') : '-' }}</td>
                            </tr>
                            <tr>
                                <td>Payment Method</td>
                                <td>{{ $order->methodPayment?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td>Created By</td>
                                <td>{{ $order->createdByUser ? ($order->createdByUser->first_name . ' ' . $order->createdByUser->last_name) : '-' }}</td>
                            </tr>
                            @if($order->notes)
                            <tr>
                                <td>Notes</td>
                                <td>{{ $order->notes }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless detail-info mb-0">
                            <tr>
                                <td>Subtotal</td>
                                <td>Rp {{ format_number($order->subtotal, 2, true) }}</td>
                            </tr>
                            @if((float)$order->item_discount_total > 0)
                            <tr>
                                <td>Item Discounts</td>
                                <td class="text-danger">- Rp {{ format_number($order->item_discount_total, 2, true) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td>Tax ({{ $order->tax_enabled ? format_number($order->tax_rate, 0) . '%' : 'Disabled' }})</td>
                                <td>Rp {{ format_number($order->tax_amount, 2, true) }}</td>
                            </tr>
                            @if((float)$order->discount_amount > 0)
                            <tr>
                                <td>
                                    Discount
                                    @if($order->discount_type === 'percent')
                                        ({{ format_number($order->discount_value, 0) }}%)
                                    @endif
                                </td>
                                <td class="text-danger">- Rp {{ format_number($order->discount_amount, 2, true) }}</td>
                            </tr>
                            @endif
                            @if((float)$order->shipping_amount > 0)
                            <tr>
                                <td>Shipping</td>
                                <td>Rp {{ format_number($order->shipping_amount, 2, true) }}</td>
                            </tr>
                            @endif
                            <tr class="border-top">
                                <td class="fw-bold fs-5" style="color:#2c3e50">Total</td>
                                <td class="fw-bold fs-5 text-primary">Rp {{ format_number($order->total, 2, true) }}</td>
                            </tr>
                            @if($order->paid_at)
                            <tr>
                                <td>Paid At</td>
                                <td>{{ $order->paid_at->format('d M Y H:i') }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Items --}}
        <div class="card mb-4">
            <h5 class="card-header">Items ({{ $order->items->count() }})</h5>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">No</th>
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Unit</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit Price</th>
                                @if($order->items->sum(fn($i) => (float)$i->discount_amount) > 0)
                                <th class="text-end">Discount</th>
                                @endif
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hasItemDiscount = $order->items->sum(fn($i) => (float)$i->discount_amount) > 0; @endphp
                            @foreach($order->items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->product?->name ?? '-' }}</td>
                                <td>
                                    @if($item->variant)
                                        {{ $item->variant->sku }}
                                        @php
                                            $attrs = $item->variant->variantAttributes
                                                ->map(fn($va) => ($va->attributeDefinition->name ?? '') . ': ' . ($va->attributeValue->value ?? ''))
                                                ->join(', ');
                                        @endphp
                                        @if($attrs)
                                            <br><small class="text-muted">{{ $attrs }}</small>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $item->unit ? ($item->unit->symbol ?: $item->unit->name) : '-' }}</td>
                                <td class="text-end">{{ format_number($item->quantity, 2, true) }}</td>
                                <td class="text-end">Rp {{ format_number($item->unit_price, 2, true) }}</td>
                                @if($hasItemDiscount)
                                <td class="text-end">
                                    @if((float)$item->discount_amount > 0)
                                        <span class="text-danger">
                                            - Rp {{ format_number($item->discount_amount, 2, true) }}
                                            @if($item->discount_type === 'percent')
                                                <small>({{ format_number($item->discount_value, 0) }}%)</small>
                                            @endif
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                @endif
                                <td class="text-end fw-semibold">Rp {{ format_number($item->subtotal, 2, true) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="{{ $hasItemDiscount ? 7 : 6 }}" class="text-end fw-bold">Total</td>
                                <td class="text-end fw-bold">Rp {{ format_number($order->items->sum(fn($i) => (float)$i->subtotal), 2, true) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Payments --}}
        @if($order->payments->count() > 0)
        <div class="card">
            <h5 class="card-header">Payments ({{ $order->payments->count() }})</h5>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">No</th>
                                <th>Payment Code</th>
                                <th>Method</th>
                                <th>Gateway</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Change</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->payments as $pi => $payment)
                            <tr>
                                <td>{{ $pi + 1 }}</td>
                                <td>{{ $payment->payment_code ?? '-' }}</td>
                                <td>{{ $payment->methodPayment?->name ?? '-' }}</td>
                                <td>
                                    @if($payment->gateway)
                                        <span class="badge bg-label-info">{{ strtoupper($payment->gateway) }}</span>
                                        @if($payment->gateway_reference)
                                            <br><small class="text-muted">{{ $payment->gateway_reference }}</small>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @php $pColor = ['completed' => 'success', 'pending' => 'warning', 'failed' => 'danger'][$payment->status] ?? 'secondary'; @endphp
                                    <span class="badge bg-label-{{ $pColor }}">{{ ucfirst($payment->status) }}</span>
                                </td>
                                <td class="text-end fw-semibold">Rp {{ format_number($payment->amount, 2, true) }}</td>
                                <td class="text-end">
                                    @if((float)$payment->change_amount > 0)
                                        Rp {{ format_number($payment->change_amount, 2, true) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $payment->created_at?->format('d M Y H:i') ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if($order->paymentGatewayCallbacks->count() > 0)
        <div class="card mt-4">
            <h5 class="card-header">Payment Gateway Callback Log ({{ $order->paymentGatewayCallbacks->count() }})</h5>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Sumber</th>
                                <th>Status Invoice</th>
                                <th>Hasil Proses</th>
                                <th>Invoice ID</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->paymentGatewayCallbacks as $cb)
                            @php
                                $resultColors = [
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'pending' => 'warning',
                                    'error' => 'danger',
                                    'ignored' => 'secondary',
                                    'skipped' => 'secondary',
                                ];
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ $cb->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td><code>{{ $cb->source }}</code></td>
                                <td>{{ $cb->invoice_status ?: '-' }}</td>
                                <td>
                                    <span class="badge bg-label-{{ $resultColors[$cb->process_result] ?? 'secondary' }}">
                                        {{ $cb->process_result }}
                                    </span>
                                </td>
                                <td><small>{{ $cb->gateway_reference ?: '-' }}</small></td>
                                <td><small class="text-muted">{{ $cb->error_message ?: '-' }}</small></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
