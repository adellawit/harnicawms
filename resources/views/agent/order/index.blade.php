@extends('layouts.agent-order')

@section('title', 'Order ke Distributor | ')

@section('shop_body_class')
    @if (count($cart['items'] ?? []) > 0) shop-has-cart-bar @endif
@endsection

@section('content')
    <header class="shop-catalog-header">
        <div class="d-flex flex-column flex-sm-row flex-sm-wrap align-items-stretch align-items-sm-center justify-content-between gap-2 gap-sm-3">
            <div class="flex-grow-1 min-w-0">
                <h1 class="shop-page-title">Order ke Distributor</h1>
                <p class="text-muted small mb-0">Katalog produk jadi</p>
            </div>
            <form method="GET" class="shop-search-form d-flex gap-2 flex-shrink-0">
                <input type="search" name="q" class="form-control" placeholder="Cari produk..."
                    value="{{ $search }}" autocomplete="off">
                <button type="submit" class="btn btn-outline-secondary flex-shrink-0" aria-label="Cari">
                    <i class="ti ti-search"></i>
                </button>
            </form>
        </div>
    </header>

    <div class="row g-2 g-sm-3" id="productGrid">
        @forelse ($products as $product)
            <div class="col-6 col-sm-4 col-lg-3">
                <div class="card shop-product-card h-100" data-product-id="{{ $product['id'] }}"
                    data-product-name="{{ $product['name'] }}">
                    <div class="thumb">
                        @if ($product['image'])
                            <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" loading="lazy"
                                onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'placeholder ti ti-package'}))">
                        @else
                            <span class="placeholder ti ti-package"></span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="product-name">{{ $product['name'] }}</div>
                        <div class="product-price">
                            Rp {{ number_format($product['min_price'], 0, ',', '.') }}
                            @if ($product['variants_count'] > 1)
                                <span class="text-muted fw-normal">+</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
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
