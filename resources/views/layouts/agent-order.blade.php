<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-theme-color-mode="{{ $appTheme['color_mode'] ?? 'logo_extract' }}"
    data-theme-primary="{{ $appTheme['primary'] ?? '#5C9E84' }}"
    data-theme-secondary="{{ $appTheme['secondary'] ?? '#7BB5A0' }}">
<head>
    @include('layouts.partials._agent-head', ['titleDefault' => 'Order ke Distributor'])
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}" />
    @stack('styles')
</head>
<body class="shop-body agent-order-body {{ ($appTheme['glass_enabled'] ?? true) ? 'app-glass-enabled' : '' }} {{ ($appTheme['motion_enabled'] ?? true) ? 'app-motion-enabled' : '' }} @auth('customer') shop-has-bottom-nav @endauth @yield('shop_body_class')">
    {{-- Full-page fixed background decoration (e.g. beranda's floating shapes)
         lives directly under <body>, as a sibling of nav/main/footer, so it
         is never confined to .shop-main's boxed/centered width. --}}
    @stack('body-top')

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

    @include('agent.partials._agent-footer')

    @auth('customer')
        @include('agent.partials._shop-cart-offcanvas')
        @include('agent.partials._agent-bottom-nav')
    @endauth

    @include('layouts.partials._agent-scripts')
    @stack('scripts')
</body>
</html>
