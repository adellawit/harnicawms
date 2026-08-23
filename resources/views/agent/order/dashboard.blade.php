@extends('layouts.agent-order')

@section('title', 'Beranda | ')

@section('shop_body_class')
    agent-dashboard-page
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
    {{-- 1. Hero sapaan + alamat --}}
    <header class="agent-dashboard-hero mb-3">
        <div class="agent-dashboard-hero-body">
            <div class="agent-dashboard-hero-text min-w-0">
                <h1 class="shop-page-title mb-2">Halo, {{ $customer->name }}</h1>
                <p class="agent-dashboard-hero-meta mb-1">
                    <i class="ti ti-building-store"></i> {{ $branchLabel ?: '-' }}
                    <span class="agent-dashboard-hero-dot">·</span>
                    <i class="ti ti-id-badge-2"></i> {{ $agentCode }}
                </p>
                <p class="agent-dashboard-hero-address mb-3">
                    <i class="ti ti-map-pin"></i> {{ $shippingAddress ?: 'Alamat belum diatur' }}
                </p>
                <a href="{{ route('agent-order.index') }}" class="btn btn-primary">
                    <i class="ti ti-shopping-cart me-1"></i> Mulai order
                </a>
            </div>
            <div class="agent-dashboard-hero-illustration d-none d-md-block">
                <x-illustration-agent-package class="dashboard-hero-illustration" />
            </div>
        </div>
    </header>

    @include('agent.order.partials._hero-promo-carousel')

    {{-- 2. Statistik --}}
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-4">
            <x-dashboard.kpi-card
                title="Pesanan aktif"
                :value="$stats['active_orders']"
                icon="ti ti-shopping-cart"
                icon-color="primary"
            />
        </div>
        <div class="col-4">
            <x-dashboard.kpi-card
                title="Order bulan ini"
                :value="$stats['orders_this_month']"
                icon="ti ti-calendar-stats"
                icon-color="info"
            />
        </div>
        <div class="col-4">
            <x-dashboard.kpi-card
                title="Reseller aktif"
                :value="$stats['active_resellers']"
                icon="ti ti-users"
                icon-color="success"
            />
        </div>
    </div>

    {{-- 3. Kartu navigasi --}}
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-md-4">
            <a href="{{ route('agent-order.pos') }}" class="card border-0 shadow-sm h-100 agent-dashboard-nav-card text-decoration-none text-body">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <span class="agent-dashboard-nav-icon agent-dashboard-nav-icon-primary"><i class="ti ti-cash"></i></span>
                    <div class="min-w-0">
                        <div class="fw-semibold">POS / Kasir</div>
                        <div class="small text-muted">Jual ke reseller Anda</div>
                    </div>
                    <i class="ti ti-chevron-right ms-auto text-muted flex-shrink-0"></i>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('agent-order.index') }}" class="card border-0 shadow-sm h-100 agent-dashboard-nav-card text-decoration-none text-body">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <span class="agent-dashboard-nav-icon agent-dashboard-nav-icon-primary"><i class="ti ti-building-store"></i></span>
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
                    <span class="agent-dashboard-nav-icon agent-dashboard-nav-icon-info"><i class="ti ti-photo"></i></span>
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
                    <span class="agent-dashboard-nav-icon agent-dashboard-nav-icon-secondary"><i class="ti ti-school"></i></span>
                    <div class="min-w-0">
                        <div class="fw-semibold">Pelatihan</div>
                        <div class="small text-muted">Materi pelatihan untuk agen</div>
                    </div>
                    <i class="ti ti-chevron-right ms-auto text-muted flex-shrink-0"></i>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('agent-order.stock') }}" class="card border-0 shadow-sm h-100 agent-dashboard-nav-card text-decoration-none text-body">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <span class="agent-dashboard-nav-icon agent-dashboard-nav-icon-success"><i class="ti ti-building-warehouse"></i></span>
                    <div class="min-w-0">
                        <div class="fw-semibold">Stok Gudang</div>
                        <div class="small text-muted">Lihat stok di gudang Anda</div>
                    </div>
                    <i class="ti ti-chevron-right ms-auto text-muted flex-shrink-0"></i>
                </div>
            </a>
        </div>
    </div>

    {{-- 4. Pesanan aktif --}}
    <section class="mb-4">
        <x-dashboard.section-header icon="ti ti-shopping-cart" title="Pesanan aktif">
            @if ($totalActiveOrders > 4)
                <a href="{{ route('agent-order.orders') }}" class="small text-primary text-decoration-none">Semua pesanan →</a>
            @endif
        </x-dashboard.section-header>
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
                            'verification' => 'warning',
                            default => 'secondary',
                        };
                        $statusLabel = match ($order->status) {
                            'verification' => 'VERIFIKASI',
                            default => strtoupper($order->status),
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
                                    <span class="badge bg-label-{{ $statusBadge }}">{{ $statusLabel }}</span>
                                    <span class="badge bg-label-{{ $payBadge }}">{{ strtoupper($order->payment_status) }}</span>
                                </div>
                            </div>
                        </a>
                    </article>
                @empty
                    <div class="list-group-item">
                        <x-empty-state
                            icon="ti ti-shopping-cart-off"
                            title="Belum ada pesanan aktif"
                            subtitle="Pesanan yang sedang berjalan akan muncul di sini."
                        />
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- 5. Order lagi --}}
    @if ($lastOrder)
        <section class="mb-4">
            <div class="card border-0 shadow-sm shop-order-card agent-reorder-card">
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

    {{-- 6. Reseller Saya --}}
    <section class="mb-4">
        <x-dashboard.section-header icon="ti ti-users" title="Reseller Saya">
            @if ($totalResellers > 4)
                <a href="{{ route('agent-order.resellers') }}" class="small text-primary text-decoration-none">Semua reseller →</a>
            @endif
        </x-dashboard.section-header>
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
                    <div class="list-group-item">
                        <x-empty-state
                            icon="ti ti-user-off"
                            title="Belum ada reseller"
                            subtitle="Reseller yang tergabung dengan Anda akan tampil di sini."
                        />
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- 7. Materi pemasaran --}}
    <section class="mb-4">
        <x-dashboard.section-header icon="ti ti-photo" title="Materi pemasaran">
            @if ($totalMarketingAssets > 4)
                <a href="{{ route('agent-order.materials') }}" class="small text-primary text-decoration-none">Lihat semua →</a>
            @endif
        </x-dashboard.section-header>
        <div class="row g-2 g-md-3">
            @forelse ($marketingAssets as $asset)
                @php
                    $thumb = $asset->thumbnail_url ?: ($asset->type === 'image' ? $asset->file_url : null);
                    $assetIcon = ['pdf' => 'ti-file-type-pdf', 'video' => 'ti-video', 'text' => 'ti-brand-whatsapp'][$asset->type] ?? 'ti-photo';
                @endphp
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100 agent-asset-card">
                        <div class="agent-asset-thumb position-relative">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="{{ $asset->title }}" loading="lazy"
                                    onerror="this.classList.add('d-none'); this.nextElementSibling.classList.replace('d-none', 'd-flex');">
                                <div class="agent-asset-thumb-ph d-none align-items-center justify-content-center">
                                    <i class="ti {{ $assetIcon }} fs-1 text-muted"></i>
                                </div>
                            @else
                                <div class="agent-asset-thumb-ph d-flex align-items-center justify-content-center">
                                    <i class="ti {{ $assetIcon }} fs-1 text-muted"></i>
                                </div>
                            @endif
                            <span class="agent-asset-badge position-absolute top-0 start-0 m-2">
                                @include('agent.order.partials._marketing-asset-type-badge', ['asset' => $asset])
                            </span>
                        </div>
                        <div class="card-body p-2 p-md-3 d-flex flex-column">
                            <div class="fw-semibold small text-truncate mb-2" title="{{ $asset->title }}">{{ $asset->title }}</div>
                            <div class="mt-auto d-flex flex-wrap gap-1">
                                @include('agent.order.partials._marketing-asset-actions', ['asset' => $asset])
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <x-empty-state
                            icon="ti ti-photo-off"
                            title="Belum ada materi"
                            subtitle="Materi pemasaran dari distributor akan tampil di sini."
                        />
                    </div>
                </div>
            @endforelse
        </div>
    </section>

    {{-- 8. Pelatihan --}}
    <section class="mb-2">
        <x-dashboard.section-header icon="ti ti-school" title="Pelatihan">
            @if ($totalCourses > 3)
                <a href="{{ route('agent-order.training') }}" class="small text-primary text-decoration-none">Lihat semua →</a>
            @endif
        </x-dashboard.section-header>
        <div class="row g-2 g-md-3">
            @forelse ($courses as $course)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('agent-order.training.show', $course->id) }}" class="card border-0 shadow-sm h-100 text-decoration-none text-body agent-course-card">
                        <div class="agent-asset-thumb position-relative">
                            @if ($course->thumbnail_url)
                                <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" loading="lazy"
                                    onerror="this.classList.add('d-none'); this.nextElementSibling.classList.replace('d-none', 'd-flex');">
                                <div class="agent-asset-thumb-ph d-none align-items-center justify-content-center">
                                    <i class="ti ti-school fs-1 text-muted"></i>
                                </div>
                            @else
                                <div class="agent-asset-thumb-ph d-flex align-items-center justify-content-center">
                                    <i class="ti ti-school fs-1 text-muted"></i>
                                </div>
                            @endif
                        </div>
                        <div class="card-body p-2 p-md-3">
                            <div class="fw-semibold mb-1 text-truncate" title="{{ $course->title }}">{{ $course->title }}</div>
                            <p class="small text-muted mb-0">{{ $course->category?->name ?: 'Materi pelatihan untuk agen' }}</p>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <x-empty-state
                            icon="ti ti-school-off"
                            title="Belum ada materi pelatihan"
                            subtitle="Materi pelatihan untuk agen akan tampil di sini."
                        />
                    </div>
                </div>
            @endforelse
        </div>
    </section>

    @include('agent.order.partials._marketing-asset-copy-script')
@endsection
