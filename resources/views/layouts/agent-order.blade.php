<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-theme-color-mode="{{ $appTheme['color_mode'] ?? 'logo_extract' }}"
    data-theme-primary="{{ $appTheme['primary'] ?? '#5C9E84' }}"
    data-theme-secondary="{{ $appTheme['secondary'] ?? '#7BB5A0' }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $appTheme['primary'] ?? '#5C9E84' }}">
    <title>@yield('title', 'Order ke Distributor') {{ $shopCompanyName ?? config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ $appTheme['favicon_url'] ?? asset('assets/img/wms/favicon.ico') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}" />
    @include('layouts.partials.theme-vars')
    <link rel="stylesheet" href="{{ asset('assets/css/design-system.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/theme-bridge.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/shop.css') }}" />
    @stack('styles')
</head>
<body class="shop-body agent-order-body {{ ($appTheme['glass_enabled'] ?? true) ? 'app-glass-enabled' : '' }} {{ ($appTheme['motion_enabled'] ?? true) ? 'app-motion-enabled' : '' }} @yield('shop_body_class')">
    <nav class="navbar navbar-expand bg-white border-bottom sticky-top shop-nav">
        <div class="container shop-main">
            <a class="navbar-brand fw-bold mb-0 d-flex align-items-center gap-2" href="{{ route('agent-order.dashboard') }}"
                aria-label="Beranda portal agen">
                @if (!empty($appTheme['logo_url']))
                    <img src="{{ $appTheme['logo_url'] }}" alt="{{ $shopCompanyName ?? config('app.name') }}"
                         data-brand-logo="{{ $appTheme['logo_url'] }}"
                         style="height: 28px; width: auto; max-width: 120px; object-fit: contain;">
                @else
                    <span>Order Agen</span>
                @endif
            </a>
            @auth('customer')
                @include('agent.partials._shop-nav-actions')
            @endauth
        </div>
    </nav>

    <main class="container shop-main shop-main-content py-3 py-md-4">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show">{{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="shop-footer border-top">
        <div class="container shop-main py-4">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="fw-bold mb-2">{{ $shopCompanyName ?? config('app.name') }}</div>
                    <p class="text-muted small mb-0">Distributor resmi produk jadi untuk jaringan agen. Order online, kirim ke alamat agen Anda.</p>
                </div>
                <div class="col-md-4">
                    <div class="fw-semibold small text-muted text-uppercase mb-2">Kontak &amp; Alamat</div>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-1"><i class="ti ti-map-pin me-1"></i>{{ $shopCompanyAddress ?? '-' }}</li>
                        <li class="mb-1"><i class="ti ti-phone me-1"></i>{{ $shopCompanyPhone ?? '-' }}</li>
                        <li><i class="ti ti-mail me-1"></i>{{ $shopCompanyEmail ?? '-' }}</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <div class="fw-semibold small text-muted text-uppercase mb-2">Lokasi</div>
                    @if (filled($shopCompanyAddress ?? null))
                        <a class="small" target="_blank" rel="noopener"
                            href="https://www.google.com/maps/search/?api=1&query={{ urlencode($shopCompanyAddress) }}">
                            <i class="ti ti-external-link me-1"></i>Buka di Google Maps
                        </a>
                    @else
                        <span class="small text-muted">-</span>
                    @endif
                </div>
            </div>
            <div class="text-center text-muted small mt-3">&copy; {{ date('Y') }} {{ $shopCompanyName ?? config('app.name') }}</div>
        </div>
    </footer>

    @auth('customer')
        @include('agent.partials._shop-cart-offcanvas')
    @endauth

    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/js/brand-theme.js') }}"></script>
    <script>
        window.shopRoutes = {
            shop: @json(route('agent-order.index')),
            variants: @json(route('agent-order.products.variants')),
            cartAdd: @json(route('agent-order.cart.add')),
            cartUpdate: @json(route('agent-order.cart.update')),
            cartRemove: @json(route('agent-order.cart.remove')),
            csrf: @json(csrf_token()),
        };
        window.shopCheckoutUrl = @json(route('agent-order.checkout'));
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': window.shopRoutes.csrf } });
    </script>
    @auth('customer')
        <script src="{{ asset('assets/js/shop.js') }}"></script>
    @endauth
    @stack('scripts')
</body>
</html>
