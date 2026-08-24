{{--
    Shared <head> block for the agent portal layouts (agent-order + agent-pos).
    Emits everything up to and including shop.css; each layout adds its own
    page-specific stylesheets after this include, then @stack('styles').

    Param: $titleDefault — fallback title when the view sets no @section('title').
--}}
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $appTheme['primary'] ?? '#5C9E84' }}">
<title>@yield('title', $titleDefault ?? 'Portal Agen') {{ $shopCompanyName ?? config('app.name') }}</title>
<link rel="icon" type="image/x-icon" href="{{ $appTheme['favicon_url'] ?? asset('assets/img/wms/favicon.ico') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}" />
@include('layouts.partials.theme-vars')
<link rel="stylesheet" href="{{ asset('assets/css/design-system.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/css/theme-bridge.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/css/shop.css') }}" />
