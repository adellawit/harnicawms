@extends('layouts.agent-order')

@section('title', $order->sales_number . ' | Riwayat POS | ')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/agent-pos.css') }}">
@endpush

@push('vendor-scripts')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endpush

@section('content')
    @php
        [$payLabel, $payBadge] = match ($order->payment_status) {
            'paid' => ['Lunas', 'success'],
            'unpaid' => ['Pending', 'warning'],
            'pending_verification' => ['Menunggu Verifikasi', 'info'],
            default => [strtoupper((string) $order->payment_status), 'secondary'],
        };
        [$statusLabel, $statusBadge] = match ($order->status) {
            'completed' => ['Selesai', 'success'],
            'pending' => ['Pending', 'info'],
            'cancelled' => ['Dibatalkan', 'danger'],
            default => [strtoupper((string) $order->status), 'secondary'],
        };
    @endphp

    <div class="pos-history-page">
        <div class="pos-history-toolbar">
            <div>
                <a href="{{ route('agent-order.pos.history') }}" class="pos-history-back-link">
                    <i class="ti ti-arrow-left"></i> Kembali ke Riwayat
                </a>
                <h1 class="pos-history-title mb-1">{{ $order->sales_number }}</h1>
                <time class="text-muted small">{{ $order->created_at->format('d M Y, H:i') }}</time>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge bg-label-{{ $payBadge }}">{{ $payLabel }}</span>
                <a href="{{ route('agent-order.pos.history.invoice-pdf', $order->id) }}" target="_blank"
                    class="btn btn-outline-primary btn-sm">
                    <i class="ti ti-file-invoice me-1"></i> Cetak Invoice
                </a>
                @if ($order->payment_status === 'unpaid')
                    <button type="button"
                        class="btn btn-success btn-sm btn-history-pay"
                        data-order-id="{{ $order->id }}"
                        data-sales-number="{{ $order->sales_number }}"
                        data-total="{{ (float) $order->total }}">
                        <i class="ti ti-cash me-1"></i> Bayar
                    </button>
                @endif
            </div>
        </div>

        @if (request()->get('paid') === '1')
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Pembayaran berhasil. Transaksi sudah lunas.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold py-3">Item Transaksi</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 pos-history-detail-table">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Unit</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Harga</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    @php
                                        $unitLabel = $item->unit?->symbol ?: ($item->unit?->name ?: $item->unit?->code);
                                        $productName = $item->product?->name ?? $item->variant?->product?->name;
                                        $productName = $productName ? product_print_name($productName) : 'Item';
                                    @endphp
                                    <tr>
                                        <td>{{ $productName }}</td>
                                        <td class="text-muted">{{ $unitLabel ?: '-' }}</td>
                                        <td class="text-end">{{ (int) $item->quantity }}</td>
                                        <td class="text-end text-nowrap">
                                            Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}
                                        </td>
                                        <td class="text-end text-nowrap fw-semibold">
                                            Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm pos-history-summary">
                    <div class="card-body">
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="small text-muted">Pelanggan / Reseller</div>
                            <div class="fw-semibold">{{ $order->customer_name ?: ($order->customer?->name ?? '-') }}</div>
                            @if ($order->customer?->code)
                                <div class="text-muted small">{{ $order->customer->code }}</div>
                            @endif
                        </div>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="small text-muted">Alamat pengiriman</div>
                            <div>{{ $order->customer_address ?: '-' }}</div>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">Subtotal</span>
                            <span>Rp {{ number_format((float) $order->subtotal, 0, ',', '.') }}</span>
                        </div>
                        @if ((float) $order->discount_amount > 0)
                            <div class="d-flex justify-content-between mb-2 small">
                                <span class="text-muted">Diskon</span>
                                <span class="text-danger">- Rp {{ number_format((float) $order->discount_amount, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">Ongkir</span>
                            <span>Rp {{ number_format((float) $order->shipping_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top fw-bold fs-5">
                            <span>Total</span>
                            <span class="text-primary">Rp {{ number_format((float) $order->total, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('agent.pos._history-pay-modal')
@endsection

@push('scripts')
    <script>
        window.agentPosHistoryConfig = {
            payPendingBase: @json(url('/agent-order/pos/payment')),
            cashMethodId: @json($cashMethodId),
        };
    </script>
    <script src="{{ asset('assets/js/agent-pos-history.js') }}"></script>
@endpush
