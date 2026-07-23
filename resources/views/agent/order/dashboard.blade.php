@extends('layouts.agent-order')

@section('title', 'Beranda | ')

@section('shop_body_class')
    agent-dashboard-page
@endsection

@section('content')
    {{-- 1. Hero sapaan --}}
    <header class="agent-dashboard-hero card border-0 shadow-sm mb-3">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
                <div class="min-w-0">
                    <h1 class="shop-page-title mb-1">Halo, {{ $customer->name }}</h1>
                    <p class="text-muted small mb-0">
                        {{ $branchLabel ?: '-' }} · Agent · {{ $agentCode }}
                    </p>
                </div>
                <a href="{{ route('agent-order.index') }}" class="btn btn-primary flex-shrink-0 align-self-start align-self-md-center">
                    <i class="ti ti-shopping-cart me-1"></i> Mulai order
                </a>
            </div>
        </div>
    </header>

    {{-- 2. Alamat kirim --}}
    <div class="card border-0 shadow-sm shop-order-card mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-start gap-2">
                <i class="ti ti-map-pin text-muted mt-1 flex-shrink-0"></i>
                <div class="min-w-0">
                    <div class="small text-muted mb-1">Alamat kirim</div>
                    <div>{{ $shippingAddress ?: 'Alamat belum diatur' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Statistik --}}
    <div class="row g-2 g-md-3 mb-3 agent-dashboard-stats">
        <div class="col-4">
            <div class="card border-0 shadow-sm h-100 agent-dashboard-stat">
                <div class="card-body py-3 text-center">
                    <div class="agent-dashboard-stat-value">{{ $stats['active_orders'] }}</div>
                    <div class="small text-muted">Pesanan aktif</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card border-0 shadow-sm h-100 agent-dashboard-stat">
                <div class="card-body py-3 text-center">
                    <div class="agent-dashboard-stat-value">{{ $stats['orders_this_month'] }}</div>
                    <div class="small text-muted">Order bulan ini</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card border-0 shadow-sm h-100 agent-dashboard-stat">
                <div class="card-body py-3 text-center">
                    <div class="agent-dashboard-stat-value">{{ $stats['active_resellers'] }}</div>
                    <div class="small text-muted">Reseller aktif</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Kartu navigasi --}}
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-md-4">
            <a href="{{ route('agent-order.index') }}" class="card border-0 shadow-sm h-100 agent-dashboard-nav-card text-decoration-none text-body">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <span class="agent-dashboard-nav-icon bg-label-primary"><i class="ti ti-building-store"></i></span>
                    <div class="min-w-0">
                        <div class="fw-semibold">Order ke Distributor</div>
                        <div class="small text-muted">Katalog produk jadi</div>
                    </div>
                    <i class="ti ti-chevron-right ms-auto text-muted flex-shrink-0"></i>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('agent-order.materials') }}" class="card border-0 shadow-sm h-100 agent-dashboard-nav-card text-decoration-none text-body">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <span class="agent-dashboard-nav-icon bg-label-info"><i class="ti ti-photo"></i></span>
                    <div class="min-w-0">
                        <div class="fw-semibold">Materi Pemasaran</div>
                        <div class="small text-muted">Brosur, poster, template WA, video</div>
                    </div>
                    <i class="ti ti-chevron-right ms-auto text-muted flex-shrink-0"></i>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('agent-order.training') }}" class="card border-0 shadow-sm h-100 agent-dashboard-nav-card text-decoration-none text-body">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <span class="agent-dashboard-nav-icon bg-label-secondary"><i class="ti ti-school"></i></span>
                    <div class="min-w-0">
                        <div class="fw-semibold">Pelatihan</div>
                        <div class="small text-muted">Materi pelatihan untuk agen</div>
                    </div>
                    <i class="ti ti-chevron-right ms-auto text-muted flex-shrink-0"></i>
                </div>
            </a>
        </div>
    </div>

    {{-- 5. Pesanan aktif --}}
    <section class="mb-4">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h2 class="h6 fw-semibold mb-0">Pesanan aktif</h2>
            <a href="{{ route('agent-order.orders') }}" class="small text-primary text-decoration-none">Semua pesanan →</a>
        </div>
        <div class="card border-0 shadow-sm shop-order-card">
            <div class="list-group list-group-flush">
                @forelse ($activeOrders as $order)
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
                    <div class="list-group-item text-center text-muted py-4">
                        Belum ada pesanan aktif.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- 6. Order lagi --}}
    @if ($lastOrder)
        <section class="mb-4">
            <div class="card border-0 shadow-sm shop-order-card">
                <div class="card-body d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3 py-3">
                    <div class="min-w-0">
                        <div class="fw-semibold">Order lagi dari {{ $lastOrder->sales_number }}</div>
                        <div class="small text-muted">
                            {{ $lastOrder->created_at->format('d M Y, H:i') }}
                            · Rp {{ number_format($lastOrder->total, 0, ',', '.') }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('agent-order.reorder', $lastOrder->id) }}" class="flex-shrink-0">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="ti ti-refresh me-1"></i> Order lagi
                        </button>
                    </form>
                </div>
            </div>
        </section>
    @endif

    {{-- 7. Reseller Saya --}}
    <section class="mb-4">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h2 class="h6 fw-semibold mb-0">Reseller Saya</h2>
            <a href="{{ route('agent-order.resellers') }}" class="small text-primary text-decoration-none">Semua reseller →</a>
        </div>
        <div class="card border-0 shadow-sm shop-order-card">
            <div class="list-group list-group-flush">
                @forelse ($resellers as $reseller)
                    @php
                        $initials = collect(explode(' ', trim($reseller->name)))
                            ->filter()
                            ->take(2)
                            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                            ->implode('');
                        $resellerBadge = $reseller->status === 'active' ? 'success' : 'secondary';
                        $resellerLabel = $reseller->status === 'active' ? 'AKTIF' : 'NONAKTIF';
                    @endphp
                    <div class="list-group-item agent-dashboard-reseller-row">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <span class="agent-dashboard-avatar">{{ $initials ?: '?' }}</span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $reseller->name }}</div>
                                <div class="small text-muted text-truncate">
                                    {{ $reseller->code ?: '-' }}
                                    @if ($reseller->city)
                                        · {{ $reseller->city }}
                                    @endif
                                    @if ($reseller->phone)
                                        · {{ $reseller->phone }}
                                    @endif
                                </div>
                            </div>
                            <span class="badge bg-label-{{ $resellerBadge }} flex-shrink-0">{{ $resellerLabel }}</span>
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-center text-muted py-4">
                        Belum ada reseller.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- 8. Materi pemasaran --}}
    <section class="mb-4">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h2 class="h6 fw-semibold mb-0">Materi pemasaran</h2>
            <a href="{{ route('agent-order.materials') }}" class="small text-primary text-decoration-none">Lihat semua →</a>
        </div>
        <div class="card border-0 shadow-sm shop-order-card">
            <div class="list-group list-group-flush">
                @forelse ($marketingAssets as $asset)
                    <div class="list-group-item agent-dashboard-asset-row">
                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-2">
                            <div class="min-w-0">
                                @include('agent.order.partials._marketing-asset-type-badge', ['asset' => $asset])
                                <span class="fw-semibold ms-1">{{ $asset->title }}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-1 flex-shrink-0">
                                @include('agent.order.partials._marketing-asset-actions', ['asset' => $asset])
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-center text-muted py-4">
                        Belum ada materi.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- 9. Pelatihan --}}
    <section class="mb-2">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h2 class="h6 fw-semibold mb-0">Pelatihan</h2>
            <a href="{{ route('agent-order.training') }}" class="small text-primary text-decoration-none">Lihat semua →</a>
        </div>
        <div class="row g-2 g-md-3">
            @forelse ($courses as $course)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('agent-order.training.show', $course->id) }}" class="card border-0 shadow-sm h-100 agent-dashboard-nav-card text-decoration-none text-body">
                        <div class="card-body d-flex flex-column">
                            <div class="fw-semibold mb-1">{{ $course->title }}</div>
                            @if ($course->description)
                                <p class="small text-muted mb-3 flex-grow-1">{{ Str::limit(strip_tags($course->description), 120) }}</p>
                            @else
                                <p class="small text-muted mb-3 flex-grow-1">Materi pelatihan</p>
                            @endif
                            <span class="small text-primary align-self-start">Buka course →</span>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center text-muted py-4">
                            Belum ada materi pelatihan.
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </section>

    @include('agent.order.partials._marketing-asset-copy-script')
@endsection
