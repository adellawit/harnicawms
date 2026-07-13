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
    <title>@yield('title', 'Shop') {{ $shopCompanyName ?? config('app.name') }}</title>
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
<body class="shop-body {{ ($appTheme['glass_enabled'] ?? true) ? 'app-glass-enabled' : '' }} {{ ($appTheme['motion_enabled'] ?? true) ? 'app-motion-enabled' : '' }} @yield('shop_body_class')">
    <nav class="navbar navbar-expand bg-white border-bottom sticky-top shop-nav">
        <div class="container shop-main">
            <a class="navbar-brand fw-bold mb-0 d-flex align-items-center gap-2" href="{{ route('customer.shop') }}">
                @if (!empty($appTheme['logo_url']))
                    <img src="{{ $appTheme['logo_url'] }}" alt="{{ $shopCompanyName ?? config('app.name') }}"
                         data-brand-logo="{{ $appTheme['logo_url'] }}"
                         style="height: 28px; width: auto; max-width: 120px; object-fit: contain;">
                @endif
                <span>{{ $shopCompanyName ?? config('shop.default_company_name', 'WWW') }}</span>
            </a>
            @auth('customer')
                @php
                    $navCustomer = auth('customer')->user();
                    $navAccountActive = request()->routeIs('customer.account');
                    $navOrdersActive = request()->routeIs('customer.orders') || request()->routeIs('customer.orders.show');
                    $navAvatarActive = $navAccountActive || $navOrdersActive;
                @endphp
                <div class="d-flex align-items-center gap-1 gap-sm-2 ms-auto shop-nav-actions">
                    <a href="{{ route('customer.shop') }}" class="btn btn-sm btn-light {{ request()->routeIs('customer.shop') ? 'active' : '' }}"
                        title="Items" aria-label="Items">
                        <i class="ti ti-layout-grid"></i>
                        <span class="d-none d-md-inline ms-1">Items</span>
                    </a>
                    <a href="{{ route('customer.checkout') }}"
                        class="shop-nav-circle shop-nav-cart {{ request()->routeIs('customer.checkout') ? 'active' : '' }}"
                        id="navCartBtn" title="Keranjang" aria-label="Keranjang">
                        <i class="ti ti-shopping-cart"></i>
                        @php $navCartCount = count(session(\App\Services\Shop\ShopCartService::SESSION_KEY)['items'] ?? []); @endphp
                        @if ($navCartCount > 0)
                            <span class="badge bg-danger rounded-pill cart-badge" id="navCartBadge">{{ $navCartCount }}</span>
                        @endif
                    </a>
                    <div class="dropdown shop-nav-avatar-dropdown">
                        <button type="button"
                            class="shop-nav-circle shop-nav-avatar border-0 p-0 {{ $navAvatarActive ? 'active' : '' }}"
                            data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false"
                            aria-label="Menu akun">
                            <span class="shop-nav-avatar-initials">{{ $navCustomer->initials() }}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shop-avatar-menu p-0 border-0 shadow">
                            <div class="shop-avatar-menu-card">
                                <div class="shop-avatar-menu-header">
                                    <div class="shop-avatar-menu-avatar" aria-hidden="true">{{ $navCustomer->initials() }}</div>
                                    <div class="min-w-0">
                                        <div class="shop-avatar-menu-name text-truncate">{{ $navCustomer->name }}</div>
                                        <div class="shop-avatar-menu-email text-truncate">{{ $navCustomer->email }}</div>
                                    </div>
                                </div>
                                <div class="shop-avatar-menu-links">
                                    <a href="{{ route('customer.account') }}"
                                        class="shop-avatar-menu-item {{ $navAccountActive ? 'active' : '' }}">
                                        <i class="ti ti-user"></i>
                                        <span>My Account</span>
                                    </a>
                                    <a href="{{ route('customer.orders') }}"
                                        class="shop-avatar-menu-item {{ $navOrdersActive ? 'active' : '' }}">
                                        <i class="ti ti-receipt"></i>
                                        <span>Orders</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
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
            shop: @json(route('customer.shop')),
            variants: @json(route('customer.products.variants')),
            cartAdd: @json(route('customer.cart.add')),
            cartUpdate: @json(route('customer.cart.update')),
            cartRemove: @json(route('customer.cart.remove')),
            csrf: @json(csrf_token()),
        };
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': window.shopRoutes.csrf } });
    </script>
    @stack('scripts')
</body>
</html>
