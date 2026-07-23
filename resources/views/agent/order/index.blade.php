@extends('layouts.agent-order')

@section('title', 'Order ke Distributor | ')

@section('shop_body_class')
    @if (count($cart['items'] ?? []) > 0) shop-has-cart-bar @endif
@endsection

@section('content')
    <div id="agentHero" class="carousel slide shop-hero mb-3" data-bs-ride="carousel">
        <div class="carousel-inner rounded-3 overflow-hidden">
            <div class="carousel-item active">
                <div class="shop-hero-slide shop-hero-slide-1">
                    <div class="shop-hero-overlay">
                        <span class="badge bg-light text-dark mb-2">CAMPAIGN</span>
                        <h2 class="h4 text-white fw-bold mb-1">Bundling Hemat Juli</h2>
                        <p class="text-white-50 mb-0 small">Order paket family &amp; dapatkan harga spesial distributor.</p>
                    </div>
                </div>
            </div>
            <div class="carousel-item">
                <div class="shop-hero-slide shop-hero-slide-2">
                    <div class="shop-hero-overlay">
                        <span class="badge bg-light text-dark mb-2">PRODUK JADI</span>
                        <h2 class="h4 text-white fw-bold mb-1">Katalog Terbaru</h2>
                        <p class="text-white-50 mb-0 small">Pilih varian, order online, kirim ke alamat agen Anda.</p>
                    </div>
                </div>
            </div>
            <div class="carousel-item">
                <div class="shop-hero-slide shop-hero-slide-3">
                    <div class="shop-hero-overlay">
                        <span class="badge bg-light text-dark mb-2">AGEN</span>
                        <h2 class="h4 text-white fw-bold mb-1">Order ke Distributor</h2>
                        <p class="text-white-50 mb-0 small">Pesan produk jadi langsung dari portal agen.</p>
                    </div>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#agentHero" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#agentHero" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>

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

    <div class="shop-chips d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('agent-order.index', array_filter(['q' => $search])) }}"
            class="btn btn-sm {{ ! ($activeCategoryId ?? null) && ! ($promoOnly ?? false) ? 'btn-primary' : 'btn-outline-secondary' }}">Semua</a>
        @foreach ($categories as $cat)
            <a href="{{ route('agent-order.index', array_filter(['q' => $search, 'category_id' => $cat->id])) }}"
                class="btn btn-sm {{ ($activeCategoryId ?? null) === $cat->id ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $cat->name }}</a>
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
            <div class="col-12 text-center text-muted py-5">
                <i class="ti ti-package-off fs-1 d-block mb-2"></i>
                Tidak ada produk jadi tersedia.
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

    <div class="offcanvas offcanvas-end shop-cart-offcanvas" tabindex="-1" id="cartOffcanvas">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title">Keranjang</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <div class="flex-grow-1 overflow-auto p-3" id="cartItemsList">
                @include('agent.order._cart-items', ['cart' => $cart, 'summary' => $summary])
            </div>
            <div class="border-top p-3 bg-light" id="cartFooter">
                @include('agent.order._cart-footer', ['summary' => $summary])
            </div>
        </div>
    </div>

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

@push('scripts')
    <script src="{{ asset('assets/js/shop.js') }}"></script>
@endpush
