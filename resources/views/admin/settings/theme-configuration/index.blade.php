<x-app-layout>
    @section('title', 'Appearance & Theme | ')

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/theme-appearance.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Settings', 'url' => 'javascript:void(0);'],
            ['label' => 'Appearance & Theme', 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('settings.theme-configuration.update') }}" enctype="multipart/form-data" id="theme-settings-form">
            @csrf
            @php
                $tokensLight = old('tokens_light', $themeView['tokens_light']);
                $tokensDark = old('tokens_dark', $themeView['tokens_dark']);
                $previewMode = old('preview_mode', $themeView['preview_mode'] ?? 'light');
                $defaultsLight = app(\App\Services\Theme\AppThemeService::class)->defaultTokens('light');
                $defaultsDark = app(\App\Services\Theme\AppThemeService::class)->defaultTokens('dark');
            @endphp
            <input type="hidden" name="preview_mode" id="preview_mode" value="{{ $previewMode }}">

            <div class="theme-studio"
                 id="theme-studio"
                 data-generate-url="{{ route('settings.theme-configuration.generate') }}"
                 data-tokens-light='@json($tokensLight)'
                 data-tokens-dark='@json($tokensDark)'
                 data-preview-mode="{{ $previewMode }}">

                <div class="theme-studio-header">
                    <div>
                        <h4>Appearance</h4>
                        <p class="subtitle">Customize the color tokens used across the app. Changes only apply after you save.</p>
                    </div>
                    <div class="theme-mode-toggle" role="group" aria-label="Preview mode">
                        <button type="button" data-mode="light" class="{{ $previewMode === 'light' ? 'active' : '' }}">Light</button>
                        <button type="button" data-mode="dark" class="{{ $previewMode === 'dark' ? 'active' : '' }}">Dark</button>
                    </div>
                </div>

                <nav class="theme-studio-tabs nav" role="tablist">
                    @foreach (['dashboard' => 'Dashboard', 'forms' => 'Forms', 'table' => 'Table', 'charts' => 'Charts', 'auth' => 'Auth', 'all' => 'All'] as $scene => $label)
                        <button type="button" class="nav-link {{ $scene === 'dashboard' ? 'active' : '' }}" data-scene-tab="{{ $scene }}">{{ $label }}</button>
                    @endforeach
                </nav>

                <div class="theme-studio-body">
                    @include('admin.settings.theme-configuration.partials.preview-shell')
                    @include('admin.settings.theme-configuration.partials.token-panel', ['tokensLight' => $tokensLight])
                </div>
            </div>
        </form>
    </div>

    @push('page-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script>
            window.ThemeAppearanceConfig = {
                csrfToken: @json(csrf_token()),
                defaultsLight: @json($defaultsLight),
                defaultsDark: @json($defaultsDark),
                fontPresets: @json($themeView['font_presets'] ?? []),
                font: @json($themeView['font'] ?? null),
            };
        </script>
        <script src="{{ asset('assets/js/theme-appearance.js') }}"></script>
    @endpush
</x-app-layout>
