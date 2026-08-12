{{-- Dynamic theme font (preset Google Font or uploaded @font-face) --}}
@php
    $font = $appTheme['font'] ?? null;
@endphp
@if (is_array($font))
    @if (! empty($font['google_url']))
        <link rel="stylesheet" href="{{ $font['google_url'] }}" id="app-theme-google-font">
    @endif
    <style id="app-theme-font">
        @if (($font['source'] ?? '') === 'upload' && ! empty($font['upload_url']))
        @font-face {
            font-family: 'AppThemeFont';
            src: url('{{ $font['upload_url'] }}') format('{{ $font['css_format'] ?? 'truetype' }}');
            font-display: swap;
            font-weight: 100 900;
            font-style: normal;
        }
        @endif
        :root {
            --brand-font-family: {!! $font['family'] !!};
            --font-primary: var(--brand-font-family);
            --bs-body-font-family: var(--brand-font-family);
        }
        html, body {
            font-family: var(--font-primary) !important;
        }
    </style>
@endif
