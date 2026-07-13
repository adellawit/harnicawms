<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light-style" dir="ltr"
    data-theme="theme-default" data-assets-path="{{ asset('assets/') }}"
    data-theme-color-mode="{{ $appTheme['color_mode'] ?? 'logo_extract' }}"
    data-theme-primary="{{ $appTheme['primary'] ?? '#5C9E84' }}"
    data-theme-secondary="{{ $appTheme['secondary'] ?? '#7BB5A0' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (app()->environment('production'))
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @endif
    @endif

    <title>@yield('title', '') {{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ $appTheme['favicon_url'] ?? asset('assets/img/wms/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/fontawesome.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/flag-icons.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/node-waves/node-waves.css') }}" />

    <!-- Page CSS -->
    @stack('page-css')

    @include('layouts.partials.theme-vars')
    <link rel="stylesheet" href="{{ asset('assets/css/design-system.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/theme-bridge.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
</head>

<body class="{{ ($appTheme['glass_enabled'] ?? true) ? 'app-glass-enabled' : '' }} {{ ($appTheme['motion_enabled'] ?? true) ? 'app-motion-enabled' : '' }}">
    <!-- Public Content Wrapper -->
    <div class="public-wrapper" style="min-height: 100vh;">
        {{ $slot }}
    </div>

    <!-- Core JS -->
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>

    <!-- Vendors JS -->
    @stack('vendor-js')

    <!-- Main JS -->
    @stack('main-js')

    <!-- Page JS -->
    @stack('page-js')

    <script src="{{ asset('assets/js/brand-theme.js') }}"></script>
</body>

</html>
