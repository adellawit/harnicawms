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
    <title>@yield('title', 'POS Agen') {{ $shopCompanyName ?? config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ $appTheme['favicon_url'] ?? asset('assets/img/wms/favicon.ico') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}" />
    @include('layouts.partials.theme-vars')
    <link rel="stylesheet" href="{{ asset('assets/css/design-system.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/theme-bridge.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/shop.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/agent-pos.css') }}">
    @stack('styles')
</head>
<body class="agent-pos-body agent-order-body">
    <div class="agent-pos-shell">
        <header class="agent-pos-header">
            <div class="agent-pos-brand">
                @if (!empty($appTheme['logo_url']))
                    <img src="{{ $appTheme['logo_url'] }}" alt="{{ $shopCompanyName ?? config('app.name') }}">
                @else
                    <i class="ti ti-receipt"></i>
                @endif
            </div>
            <div class="agent-pos-identity">
                @auth('customer')
                    <span class="agent-pos-agent-name">{{ auth('customer')->user()->name }}</span>
                @endauth
                <span class="pos-meta-sep">·</span>
                <span>{{ date('d M Y') }}</span>
                <span class="pos-meta-sep">·</span>
                <span id="posClock">{{ date('H:i') }}</span>
                <span class="pos-meta-sep">·</span>
                <span><span id="cartItemCount" class="meta-val">0</span> item</span>
            </div>
            @include('agent.partials._shop-nav-actions')
        </header>

        @include('agent.partials._shop-cart-offcanvas')

        @yield('content')
    </div>

    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    @auth('customer')
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
        <script src="{{ asset('assets/js/shop.js') }}"></script>
    @endauth
    @stack('scripts')
</body>
</html>
