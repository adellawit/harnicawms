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
            <a class="navbar-brand fw-bold mb-0 d-flex align-items-center gap-2" href="{{ route('agent-order.index') }}"
                aria-label="Order ke Distributor">
                @if (!empty($appTheme['logo_url']))
                    <img src="{{ $appTheme['logo_url'] }}" alt="{{ $shopCompanyName ?? config('app.name') }}"
                         data-brand-logo="{{ $appTheme['logo_url'] }}"
                         style="height: 28px; width: auto; max-width: 120px; object-fit: contain;">
                @else
                    <span>Order Agen</span>
                @endif
            </a>
            @auth('customer')
                @php
                    $navCustomer = auth('customer')->user();
                    $navOrdersActive = request()->routeIs('agent-order.orders') || request()->routeIs('agent-order.orders.show');
                @endphp
                <div class="d-flex align-items-center gap-1 gap-sm-2 ms-auto shop-nav-actions">
                    <a href="{{ route('agent-order.index') }}" class="btn btn-sm btn-light {{ request()->routeIs('agent-order.index') ? 'active' : '' }}"
                        title="Katalog" aria-label="Katalog">
                        <i class="ti ti-layout-grid"></i>
                        <span class="d-none d-md-inline ms-1">Katalog</span>
                    </a>
                    <a href="{{ route('agent-order.checkout') }}"
                        class="shop-nav-circle shop-nav-cart {{ request()->routeIs('agent-order.checkout') ? 'active' : '' }}"
                        id="navCartBtn" title="Keranjang" aria-label="Keranjang">
                        <i class="ti ti-shopping-cart"></i>
                        @php $navCartCount = count(session(\App\Services\Shop\ShopCartService::SESSION_KEY)['items'] ?? []); @endphp
                        @if ($navCartCount > 0)
                            <span class="badge bg-danger rounded-pill cart-badge" id="navCartBadge">{{ $navCartCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('agent-order.orders') }}"
                        class="shop-nav-circle {{ $navOrdersActive ? 'active' : '' }}"
                        title="Pesanan" aria-label="Pesanan">
                        <i class="ti ti-receipt"></i>
                    </a>
                    <form method="POST" action="{{ route('agent-order.logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="shop-nav-circle border-0 bg-transparent" title="Logout" aria-label="Logout">
                            <i class="ti ti-logout"></i>
                        </button>
                    </form>
                </div>
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
        @yield('content')
    </main>

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
    @stack('scripts')
</body>
</html>
