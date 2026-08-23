@extends('layouts.agent-order')

@section('title', 'Pesanan | ')

@section('content')
    <header class="shop-page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="small text-muted text-uppercase">Portal Agen · Web Order</div>
            <h1 class="shop-page-title mb-0">Riwayat Pesanan</h1>
            <p class="text-muted small mb-0">Pantau status order ke distributor dan buka detail setiap transaksi.</p>
        </div>
        <a href="{{ route('agent-order.index') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i>Order baru</a>
    </header>

    @php
        $filters = ['all' => 'Semua', 'verification' => 'Verifikasi', 'pending' => 'Pending', 'completed' => 'Selesai', 'unpaid' => 'Belum bayar', 'cancelled' => 'Dibatalkan'];
    @endphp
    <div class="shop-chips d-flex flex-wrap gap-2 mb-3">
        @foreach ($filters as $key => $label)
            <a href="{{ route('agent-order.orders', $key === 'all' ? [] : ['filter' => $key]) }}"
                class="btn btn-sm {{ $activeFilter === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm shop-order-card">
        <div class="list-group list-group-flush">
            @forelse ($orders as $order)
                @php
                    $payBadge = match ($order->payment_status) {
                        'paid' => 'success',
                        'unpaid' => 'warning',
                        default => 'secondary',
                    };
                    $statusBadge = match ($order->status) {
                        'cancelled' => 'danger',
                        'completed' => 'success',
                        'pending' => 'info',
                        'verification' => 'warning',
                        default => 'secondary',
                    };
                    $statusLabel = match ($order->status) {
                        'verification' => 'VERIFIKASI',
                        default => strtoupper($order->status),
                    };
                    $firstItem = $order->items->first();
                    $thumb = $firstItem?->product?->image;
                    $itemCount = $order->items_count ?? $order->items->count();
                    $payMethod = $order->methodPayment?->name ?? $order->payments->first()?->methodPayment?->name;
                @endphp
                <article class="list-group-item shop-order-row">
                    <a href="{{ route('agent-order.orders.show', $order->id) }}"
                        class="shop-order-row-main text-decoration-none text-body d-flex gap-3 align-items-center">
                        <span class="shop-order-thumb flex-shrink-0">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="" loading="lazy"
                                    onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'ti ti-package'}))">
                            @else
                                <span class="ti ti-package"></span>
                            @endif
                        </span>
                        <span class="flex-grow-1 min-w-0">
                            <div class="shop-order-row-top">
                                <span class="shop-order-number">{{ $order->sales_number }}</span>
                                <span class="shop-order-total">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                            </div>
                            <div class="shop-order-row-meta">
                                <time class="shop-order-date text-muted">
                                    {{ $order->sales_date?->format('d M Y, H:i') ?? $order->created_at->format('d M Y, H:i') }}
                                    · {{ $itemCount }} item
                                </time>
                                <div class="shop-order-badges">
                                    <span class="badge bg-label-{{ $statusBadge }}">{{ $statusLabel }}</span>
                                    <span class="badge bg-label-{{ $payBadge }}">{{ strtoupper($order->payment_status) }}</span>
                                    @if ($payMethod)
                                        <span class="badge bg-label-secondary"><i class="ti ti-credit-card me-1"></i>{{ $payMethod }}</span>
                                    @endif
                                </div>
                            </div>
                        </span>
                    </a>
                </article>
            @empty
                <div class="list-group-item shop-empty-state text-center text-muted py-5">
                    <i class="ti ti-receipt-off d-block fs-1 mb-2 opacity-50"></i>
                    @if ($activeFilter !== 'all')
                        Belum ada pesanan pada filter ini.
                    @else
                        Belum ada pesanan.
                        <div class="mt-3">
                            <a href="{{ route('agent-order.index') }}" class="btn btn-primary btn-sm">Mulai order</a>
                        </div>
                    @endif
                </div>
            @endforelse
        </div>
        @if ($orders->hasPages())
            <div class="card-footer bg-white shop-pagination-footer">
                {{ $orders->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
