<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-theme-color-mode="{{ $appTheme['color_mode'] ?? 'logo_extract' }}"
    data-theme-primary="{{ $appTheme['primary'] ?? '#5C9E84' }}"
    data-theme-secondary="{{ $appTheme['secondary'] ?? '#7BB5A0' }}">
<head>
    @include('layouts.partials._agent-head', ['titleDefault' => 'POS Agen'])
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/agent-pos.css') }}">
    @stack('styles')

    {{-- Butuh jQuery lebih dulu, jadi dititipkan ke stack di _agent-scripts --}}
    @push('vendor-scripts')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    @endpush
</head>
<body class="agent-pos-body agent-order-body @yield('shop_body_class')">
    {{-- Full-page fixed background decoration, same convention as
         layouts.agent-order — see that file for the rationale. --}}
    @stack('body-top')

    <div class="agent-pos-shell">
        <header class="agent-pos-header">
            <a class="agent-pos-brand" href="{{ route('agent-order.dashboard') }}" aria-label="Beranda portal agen">
                @if (!empty($appTheme['logo_url']))
                    <img src="{{ $appTheme['logo_url'] }}" alt="{{ $shopCompanyName ?? config('app.name') }}"
                        data-brand-logo="{{ $appTheme['logo_url'] }}">
                @else
                    <i class="ti ti-receipt"></i>
                @endif
            </a>
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
            @include('agent.partials._shop-nav-actions', ['showMenu' => false])
        </header>

        @include('agent.partials._shop-cart-offcanvas')

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show m-2 mb-0">{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show m-2 mb-0">{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show m-2 mb-0">{{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>

    @include('layouts.partials._agent-scripts')
    @stack('scripts')
</body>
</html>
