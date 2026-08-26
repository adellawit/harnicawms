@extends('layouts.agent-order')

@section('title', 'Reseller | ')

@section('shop_body_class')
    agent-resellers-page
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
            <h1 class="shop-page-title mb-0">Reseller Saya</h1>
            <p class="text-muted small mb-0">Reseller yang ter-mapping ke agen Anda.</p>
        </div>
        <a href="{{ route('agent-order.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-arrow-left me-1"></i>Kembali
        </a>
    </header>

    <form method="GET" action="{{ route('agent-order.resellers') }}" class="mb-3">
        @if ($activeStatus !== 'all')
            <input type="hidden" name="status" value="{{ $activeStatus }}">
        @endif
        <div class="input-group">
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Cari nama atau kode reseller…" aria-label="Cari reseller">
            <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1"></i>Cari</button>
        </div>
    </form>

    @php
        $statusFilters = ['all' => 'Semua', 'active' => 'Aktif', 'inactive' => 'Nonaktif'];
    @endphp
    <div class="shop-chips d-flex flex-wrap gap-2 mb-3">
        @foreach ($statusFilters as $key => $label)
            @php
                $params = array_filter([
                    'q' => $search !== '' ? $search : null,
                    'status' => $key === 'all' ? null : $key,
                ]);
            @endphp
            <a href="{{ route('agent-order.resellers', $params) }}"
                class="btn btn-sm {{ $activeStatus === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
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
                    $location = collect([$reseller->city, $reseller->province])->filter()->implode(', ');
                    $waPhone = $reseller->phone ? preg_replace('/[^0-9]/', '', $reseller->phone) : null;
                @endphp
                <article class="list-group-item agent-dashboard-reseller-row">
                    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-3 min-w-0">
                        <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
                            <span class="agent-dashboard-avatar">{{ $initials ?: '?' }}</span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $reseller->name }}</div>
                                <div class="small text-muted text-truncate">
                                    {{ $reseller->code ?: '-' }}
                                    @if ($location)
                                        · {{ $location }}
                                    @endif
                                </div>
                                @if ($reseller->phone)
                                    <div class="small text-muted mt-1">
                                        <i class="ti ti-phone me-1"></i>{{ $reseller->phone }}
                                    </div>
                                @endif
                            </div>
                            <span class="badge bg-label-{{ $resellerBadge }} flex-shrink-0 d-none d-sm-inline">{{ $resellerLabel }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-sm-0 ms-5 ps-sm-0 ps-3">
                            <span class="badge bg-label-{{ $resellerBadge }} flex-shrink-0 d-sm-none">{{ $resellerLabel }}</span>
                            @if ($reseller->phone)
                                <a href="tel:{{ $reseller->phone }}" class="btn btn-sm btn-outline-secondary" title="Telepon">
                                    <i class="ti ti-phone"></i>
                                </a>
                                @if ($waPhone)
                                    <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener"
                                        class="btn btn-sm btn-outline-success" title="WhatsApp">
                                        <i class="ti ti-brand-whatsapp"></i>
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="list-group-item">
                    <x-empty-state icon="ti ti-users-off"
                        :title="$search !== '' || $activeStatus !== 'all' ? 'Tidak ada reseller pada filter ini' : 'Belum ada reseller'" />
                </div>
            @endforelse
        </div>
        @if ($resellers->hasPages())
            <div class="card-footer bg-white shop-pagination-footer">
                {{ $resellers->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
