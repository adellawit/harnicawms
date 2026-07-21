@extends('layouts.agent-order')

@section('title', 'Pesanan | ')

@section('content')
    <header class="shop-page-header">
        <h1 class="shop-page-title mb-0">Pesanan Saya</h1>
    </header>

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
                        default => 'secondary',
                    };
                @endphp
                <article class="list-group-item shop-order-row">
                    <a href="{{ route('agent-order.orders.show', $order->id) }}"
                        class="shop-order-row-main text-decoration-none text-body">
                        <div class="shop-order-row-top">
                            <span class="shop-order-number">{{ $order->sales_number }}</span>
                            <span class="shop-order-total">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                        </div>
                        <div class="shop-order-row-meta">
                            <time class="shop-order-date text-muted">
                                {{ $order->sales_date?->format('d M Y, H:i') ?? $order->created_at->format('d M Y, H:i') }}
                            </time>
                            <div class="shop-order-badges">
                                <span class="badge bg-label-{{ $statusBadge }}">{{ strtoupper($order->status) }}</span>
                                <span class="badge bg-label-{{ $payBadge }}">{{ strtoupper($order->payment_status) }}</span>
                            </div>
                        </div>
                    </a>
                </article>
            @empty
                <div class="list-group-item shop-empty-state text-center text-muted py-5">
                    <i class="ti ti-receipt-off d-block fs-1 mb-2 opacity-50"></i>
                    Belum ada pesanan.
                    <div class="mt-3">
                        <a href="{{ route('agent-order.index') }}" class="btn btn-primary btn-sm">Mulai order</a>
                    </div>
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
