@php
    $logoUrl = $themeView['logo_url'] ?? asset('assets/img/harnica/logo.png');
@endphp
<div id="theme-preview" data-preview-root>
    <div class="theme-preview-shell">
        <aside class="theme-preview-sidebar">
            <div class="theme-preview-brand">
                <img src="{{ $logoUrl }}" alt="Logo" id="themePreviewLogo">
                <span>{{ $holdingName ?? config('app.name', 'Company') }}</span>
            </div>
            <div class="theme-preview-menu-item active">
                <i class="ti ti-layout-dashboard"></i> Dashboard
            </div>
            <div class="theme-preview-menu-label">Master Data</div>
            <div class="theme-preview-menu-item"><i class="ti ti-building"></i> Companies</div>
            <div class="theme-preview-menu-item"><i class="ti ti-users"></i> Users</div>
            <div class="theme-preview-menu-label">General Ledger</div>
            <div class="theme-preview-menu-item"><i class="ti ti-report-money"></i> Journal Entry</div>
            <div class="theme-preview-menu-item"><i class="ti ti-chart-bar"></i> Balance Sheet</div>
            <div class="theme-preview-menu-item"><i class="ti ti-cash"></i> Cash & Bank</div>
        </aside>
        <div class="theme-preview-main">
            <div class="theme-preview-navbar">
                <span>Dashboard</span>
                <span class="d-flex align-items-center gap-2">
                    <span class="tp-avatar">AD</span>
                    <span style="font-weight:500;font-size:0.8rem;">Admin</span>
                </span>
            </div>
            <div class="theme-preview-content" data-scene="dashboard">
                @include('admin.settings.theme-configuration.partials.preview-scenes.dashboard')
            </div>
            <div class="theme-preview-content" data-scene="forms" hidden>
                @include('admin.settings.theme-configuration.partials.preview-scenes.forms')
            </div>
            <div class="theme-preview-content" data-scene="table" hidden>
                @include('admin.settings.theme-configuration.partials.preview-scenes.table')
            </div>
            <div class="theme-preview-content" data-scene="charts" hidden>
                @include('admin.settings.theme-configuration.partials.preview-scenes.charts')
            </div>
            <div class="theme-preview-content" data-scene="auth" hidden>
                @include('admin.settings.theme-configuration.partials.preview-scenes.auth')
            </div>
            <div class="theme-preview-content" data-scene="all" hidden>
                @include('admin.settings.theme-configuration.partials.preview-scenes.dashboard')
                @include('admin.settings.theme-configuration.partials.preview-scenes.forms')
                @include('admin.settings.theme-configuration.partials.preview-scenes.table')
            </div>
        </div>
    </div>
</div>
