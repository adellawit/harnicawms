@extends('layouts.agent-pos')

@section('title', 'Riwayat Transaksi POS | ')

@section('shop_body_class')
    agent-pos-history-page
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
    <div class="pos-history-page">
        <div class="pos-history-toolbar">
            <div>
                <h1 class="pos-history-title">Riwayat Transaksi POS</h1>
                <p class="pos-history-subtitle text-muted mb-0">Daftar transaksi POS agen — lunas & pending</p>
            </div>
            <a href="{{ route('agent-order.pos') }}" class="btn btn-primary btn-sm">
                <i class="ti ti-receipt me-1"></i> Kembali ke POS
            </a>
        </div>

        @if (request()->get('paid') === '1')
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Pembayaran berhasil. Transaksi sudah lunas.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif

        <form method="GET" action="{{ route('agent-order.pos.history') }}" class="pos-history-filters card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small mb-1" for="historySearch">Cari</label>
                        <input type="search" id="historySearch" name="q" class="form-control form-control-sm"
                            value="{{ $searchQuery }}" placeholder="No. transaksi atau nama pelanggan">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1" for="historyStatus">Status</label>
                        <select id="historyStatus" name="status" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            <option value="paid" @selected($statusFilter === 'paid')>Lunas</option>
                            <option value="unpaid" @selected($statusFilter === 'unpaid')>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ti ti-filter me-1"></i> Filter
                        </button>
                        @if ($searchQuery !== '' || $statusFilter)
                            <a href="{{ route('agent-order.pos.history') }}" class="btn btn-label-secondary btn-sm">Reset</a>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        <div class="card border-0 shadow-sm pos-history-table-card">
            @if ($orders->isEmpty())
                <div class="pos-history-empty">
                    <i class="ti ti-receipt-off"></i>
                    <p class="mb-1 fw-semibold">Belum ada transaksi POS</p>
                    <p class="text-muted small mb-3">Transaksi dari POS akan muncul di sini.</p>
                    <a href="{{ route('agent-order.pos') }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-receipt me-1"></i> Buka POS
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 pos-history-table">
                        <thead>
                            <tr>
                                <th class="text-muted small">No</th>
                                <th>No. Transaksi</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $idx => $order)
                                @php
                                    [$payLabel, $payBadge] = match ($order->payment_status) {
                                        'paid' => ['Lunas', 'success'],
                                        'unpaid' => ['Pending', 'warning'],
                                        'pending_verification' => ['Menunggu Verifikasi', 'info'],
                                        default => [strtoupper((string) $order->payment_status), 'secondary'],
                                    };
                                @endphp
                                <tr>
                                    <td class="text-muted small">{{ $orders->firstItem() + $idx }}</td>
                                    <td>
                                        <span class="fw-semibold">{{ $order->sales_number }}</span>
                                    </td>
                                    <td class="text-nowrap small">{{ $order->created_at->format('d M Y, H:i') }}</td>
                                    <td>
                                        <span class="d-block">{{ $order->customer_name ?: ($order->customer?->name ?? '-') }}</span>
                                        @if ($order->customer?->code)
                                            <span class="text-muted small">{{ $order->customer->code }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold text-nowrap">
                                        Rp {{ number_format((float) $order->total, 0, ',', '.') }}
                                    </td>
                                    <td>
                                        <span class="badge bg-label-{{ $payBadge }}">{{ $payLabel }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('agent-order.pos.history.show', $order->id) }}"
                                            class="btn btn-sm btn-label-primary">
                                            <i class="ti ti-eye me-1"></i> Lihat
                                        </a>
                                        @if ($order->payment_status === 'unpaid')
                                            <button type="button"
                                                class="btn btn-sm btn-success btn-history-pay"
                                                data-order-id="{{ $order->id }}"
                                                data-sales-number="{{ $order->sales_number }}"
                                                data-total="{{ (float) $order->total }}">
                                                <i class="ti ti-cash me-1"></i> Bayar
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($orders->hasPages())
                    <div class="card-footer bg-white border-top-0">
                        {{ $orders->links() }}
                    </div>
                @endif
            @endif
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
