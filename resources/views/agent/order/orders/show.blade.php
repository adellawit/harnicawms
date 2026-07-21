@extends('layouts.agent-order')

@section('title', $order->sales_number . ' | ')

@section('content')
    @if ($paymentBanner === 'success')
        <div class="alert alert-success shop-alert">Pembayaran berhasil. Terima kasih!</div>
    @elseif ($paymentBanner === 'pending')
        <div class="alert alert-warning shop-alert d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
            <span class="flex-grow-1">Menunggu konfirmasi pembayaran.</span>
            <button type="button" class="btn btn-sm btn-warning flex-shrink-0" id="btnSyncPay">Cek status</button>
        </div>
    @elseif ($paymentBanner === 'failed')
        <div class="alert alert-danger shop-alert">Pembayaran gagal atau dibatalkan.</div>
    @endif

    @php
        $payBadge = match ($order->payment_status) {
            'paid' => 'success',
            'unpaid' => 'warning',
            default => 'secondary',
        };
        $statusBadge = match ($order->status) {
            'completed' => 'success',
            'pending' => 'info',
            'cancelled' => 'danger',
            default => 'secondary',
        };
        $pendingPay = $order->payments->first(fn ($p) => $p->gateway === 'xendit' && $p->status === 'pending' && $p->gateway_url);
    @endphp

    <header class="shop-order-detail-header">
        <a href="{{ route('agent-order.orders') }}" class="shop-back-link">
            <i class="ti ti-arrow-left"></i> Kembali
        </a>
        <div class="shop-order-detail-title-row">
            <div class="min-w-0">
                <h1 class="shop-page-title shop-order-detail-number mb-1">{{ $order->sales_number }}</h1>
                <time class="text-muted small">{{ $order->created_at->format('d M Y, H:i') }}</time>
            </div>
            <div class="shop-order-badges shop-order-detail-badges">
                <span class="badge bg-label-{{ $statusBadge }}">{{ strtoupper($order->status) }}</span>
                <span class="badge bg-label-{{ $payBadge }}">{{ strtoupper($order->payment_status) }}</span>
            </div>
        </div>
    </header>

    <div class="shop-order-detail-layout">
        <div class="card border-0 shadow-sm shop-order-card mb-3">
            <div class="card-header bg-white fw-semibold py-3">Item</div>
            <ul class="list-group list-group-flush">
                @foreach ($order->items as $item)
                    <li class="list-group-item shop-order-detail-item">
                        <span class="shop-order-detail-item-name">
                            <span class="shop-order-detail-qty">{{ (int) $item->quantity }}×</span>
                            {{ $item->product?->name ?? $item->variant?->display_name ?? 'Item' }}
                            @php
                                $unitLabel = $item->unit?->symbol ?: ($item->unit?->name ?: $item->unit?->code);
                            @endphp
                            @if ($unitLabel)
                                <span class="text-muted fw-normal">{{ $unitLabel }}</span>
                            @endif
                        </span>
                        <span class="shop-order-detail-item-price">
                            Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card border-0 shadow-sm shop-order-card shop-order-detail-summary">
            <div class="card-body py-3">
                <div class="mb-3 pb-3 border-bottom">
                    <div class="small text-muted">Alamat pengiriman</div>
                    <div>{{ $order->customer_address ?: '-' }}</div>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Subtotal</span>
                    <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>
                @if ($order->tax_enabled)
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>PPN</span>
                        <span>Rp {{ number_format($order->tax_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="d-flex justify-content-between mb-2 small text-muted">
                    <span>Ongkos Kirim</span>
                    <span>Rp {{ number_format($order->shipping_amount ?? 0, 0, ',', '.') }}</span>
                </div>
                <div class="d-flex justify-content-between fw-bold shop-order-detail-total border-top pt-2 mt-2">
                    <span>Total</span>
                    <span class="text-primary">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                </div>

                @if ($pendingPay && $order->payment_status !== 'paid')
                    <a href="{{ $pendingPay->gateway_url }}" class="btn btn-primary w-100 mt-3 shop-order-pay-btn" target="_blank" rel="noopener">
                        Lanjutkan pembayaran
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if ($paymentBanner === 'pending')
        <script>
            $('#btnSyncPay').on('click', function () {
                $.get(@json(route('agent-order.payment.status', $order->id)), { sync: 1 })
                    .done(function (res) {
                        if (res.data?.is_paid) location.reload();
                        else alert('Pembayaran masih pending.');
                    });
            });
        </script>
    @endif
@endpush
