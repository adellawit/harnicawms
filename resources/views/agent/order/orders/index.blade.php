@extends('layouts.agent-order')

@section('title', 'Pesanan | ')

@section('shop_body_class')
    shop-orders-page
@endsection

@push('body-top')
    <div class="bg-shapes" aria-hidden="true">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>
@endpush

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
        <div class="table-responsive">
            <table class="table align-middle mb-0 shop-orders-table">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>No. Order</th>
                        <th>Tanggal</th>
                        <th>Item</th>
                        <th>Status</th>
                        <th>Pembayaran</th>
                        <th class="text-end">Total</th>
                        <th class="text-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
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
                        <tr>
                            <td class="text-muted">{{ $orders->firstItem() + $loop->index }}</td>
                            <td>
                                <a href="{{ route('agent-order.orders.show', $order->id) }}" class="fw-semibold text-decoration-none">
                                    {{ $order->sales_number }}
                                </a>
                            </td>
                            <td class="text-muted small text-nowrap">
                                {{ $order->sales_date?->format('d M Y, H:i') ?? $order->created_at->format('d M Y, H:i') }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="shop-order-thumb flex-shrink-0">
                                        @if ($thumb)
                                            <img src="{{ $thumb }}" alt="" loading="lazy"
                                                onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'ti ti-package'}))">
                                        @else
                                            <span class="ti ti-package"></span>
                                        @endif
                                    </span>
                                    <span class="text-muted small text-nowrap">{{ $itemCount }} item</span>
                                </div>
                            </td>
                            <td><span class="badge bg-label-{{ $statusBadge }}">{{ $statusLabel }}</span></td>
                            <td>
                                <span class="badge bg-label-{{ $payBadge }}">{{ strtoupper($order->payment_status) }}</span>
                                @if ($payMethod)
                                    <div class="text-muted small text-nowrap mt-1"><i class="ti ti-credit-card me-1"></i>{{ $payMethod }}</div>
                                @endif
                            </td>
                            <td class="text-end fw-semibold text-nowrap">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                            <td>
                                <div class="d-flex flex-column gap-1 shop-order-actions">
                                    <a href="{{ route('agent-order.orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-eye me-1"></i>Detail
                                    </a>
                                    @if ($order->status === 'shipped' && ! $order->received_at)
                                        <form method="POST" action="{{ route('agent-order.orders.receive', $order->id) }}"
                                              onsubmit="return confirm('Konfirmasi barang sudah diterima? Stok akan masuk ke gudang Anda.');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                                <i class="ti ti-package-import me-1"></i>Penerimaan
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('agent-order.orders.po-pdf', $order->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-file-text me-1"></i>Print PO
                                    </a>
                                    <a href="{{ route('agent-order.orders.invoice-pdf', $order->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-receipt me-1"></i>Print Invoice
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="shop-empty-state text-center text-muted py-5">
                                <i class="ti ti-receipt-off d-block fs-1 mb-2 opacity-50"></i>
                                @if ($activeFilter !== 'all')
                                    Belum ada pesanan pada filter ini.
                                @else
                                    Belum ada pesanan.
                                    <div class="mt-3">
                                        <a href="{{ route('agent-order.index') }}" class="btn btn-primary btn-sm">Mulai order</a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="card-footer bg-white shop-pagination-footer">
                {{ $orders->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
