@extends('layouts.agent-order')

@section('title', 'Order ke Distributor | ')

@section('shop_body_class')
    agent-catalog-page @if (count($cart['items'] ?? []) > 0) shop-has-cart-bar @endif
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
    @include('agent.order.partials._hero-promo-carousel')

    <header class="shop-catalog-header">
        <div class="d-flex flex-column flex-sm-row flex-sm-wrap align-items-stretch align-items-sm-center justify-content-between gap-2 gap-sm-3">
            <div class="flex-grow-1 min-w-0">
                <h1 class="shop-page-title">Order ke Distributor</h1>
                <p class="text-muted small mb-0">Katalog produk jadi</p>
            </div>
            <form method="GET" class="shop-search-form d-flex gap-2 flex-shrink-0">
                @if ($activeCategoryId ?? null)
                    <input type="hidden" name="category_id" value="{{ $activeCategoryId }}">
                @endif
                @if ($promoOnly ?? false)
                    <input type="hidden" name="promo" value="1">
                @endif
                <input type="search" name="q" class="form-control" placeholder="Cari produk..."
                    value="{{ $search }}" autocomplete="off">
                <button type="submit" class="btn btn-outline-secondary flex-shrink-0" aria-label="Cari">
                    <i class="ti ti-search"></i>
                </button>
            </form>
        </div>
    </header>

    @php
        $chipPalette = ['chip-teal', 'chip-amber', 'chip-blue', 'chip-rose', 'chip-violet'];
    @endphp
    <div class="shop-chips d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('agent-order.index', array_filter(['q' => $search])) }}"
            class="shop-chip {{ ! ($activeCategoryId ?? null) && ! ($promoOnly ?? false) ? 'shop-chip-active' : 'chip-teal' }}">
            <i class="ti ti-apps"></i> Semua
        </a>
        @foreach ($categories as $i => $cat)
            <a href="{{ route('agent-order.index', array_filter(['q' => $search, 'category_id' => $cat->id])) }}"
                class="shop-chip {{ ($activeCategoryId ?? null) === $cat->id ? 'shop-chip-active' : $chipPalette[$i % count($chipPalette)] }}">
                <i class="ti ti-tag"></i> {{ $cat->name }}
            </a>
        @endforeach
    </div>

    @if (($promoProducts ?? collect())->isNotEmpty() && ! ($promoOnly ?? false))
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h2 class="h6 mb-0"><i class="ti ti-discount-2 me-1"></i>Item Promo</h2>
            <a href="{{ route('agent-order.index', array_filter(['q' => $search, 'promo' => 1])) }}" class="small">Lihat semua →</a>
        </div>
        <div class="row g-2 g-sm-3 mb-4">
            @foreach ($promoProducts as $product)
                @include('agent.order._product-card', ['product' => $product, 'showPromo' => true])
            @endforeach
        </div>
    @endif

    <h2 class="h6 mb-2"><i class="ti ti-package me-1"></i>{{ ($promoOnly ?? false) ? 'Produk Promo' : 'Semua produk' }}</h2>

    <div class="row g-2 g-sm-3" id="productGrid">
        @forelse ($products as $product)
            @include('agent.order._product-card', ['product' => $product])
        @empty
            <div class="col-12">
                <x-empty-state icon="ti ti-package-off" title="Tidak ada produk jadi tersedia" />
            </div>
        @endforelse
    </div>

    @if (count($cart['items'] ?? []) > 0)
        <div class="shop-cart-bar d-lg-none">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="min-w-0">
                    <div class="small text-muted">{{ $summary['item_count'] }} item</div>
                    <div class="fw-bold text-truncate">Rp {{ number_format($summary['total'], 0, ',', '.') }}</div>
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="offcanvas"
                        data-bs-target="#cartOffcanvas">Keranjang</button>
                    <a href="{{ route('agent-order.checkout') }}" class="btn btn-primary btn-sm">Checkout</a>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade" id="variantModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-truncate pe-2" id="variantModalTitle">Pilih varian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-0" id="variantModalBody">
                    <div class="text-center py-4 text-muted">Memuat...</div>
                </div>
            </div>
        </div>
    </div>
@endsection
