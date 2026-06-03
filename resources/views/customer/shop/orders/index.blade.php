@extends('layouts.customer')

@section('title', 'Orders | ')

@section('content')
    <header class="shop-page-header">
        <h1 class="shop-page-title mb-0">Orders</h1>
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
                    $canCancel = $order->isCancellableByCustomer();
                @endphp
                <article class="list-group-item shop-order-row">
                    <a href="{{ route('customer.orders.show', $order->id) }}"
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
                    @if ($canCancel)
                        <form method="POST" action="{{ route('customer.orders.cancel', $order->id) }}"
                            class="shop-order-cancel-form"
                            data-order-number="{{ $order->sales_number }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger shop-order-cancel-btn w-100">
                                <i class="ti ti-x me-1"></i>Cancel
                            </button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="list-group-item shop-empty-state text-center text-muted py-5">
                    <i class="ti ti-receipt-off d-block fs-1 mb-2 opacity-50"></i>
                    Belum ada pesanan.
                    <div class="mt-3">
                        <a href="{{ route('customer.shop') }}" class="btn btn-primary btn-sm">Mulai belanja</a>
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

@push('scripts')
    <script>
        document.querySelectorAll('.shop-order-cancel-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                const num = form.getAttribute('data-order-number') || 'pesanan ini';
                if (!confirm('Batalkan ' + num + '?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
@endpush
