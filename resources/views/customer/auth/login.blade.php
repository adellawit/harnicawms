<x-guest-layout>
    @php
        $isAgentPortal = ($portal ?? 'shop') === 'agent';
        $brandLogo = $appTheme['logo_url'] ?? asset('assets/img/harnica/logo.png');
        $brandName = config('shop.default_company_name', 'WWW');
    @endphp

    @section('title', ($isAgentPortal ? 'Agent Login' : 'Customer Login') . ' | ')

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
    @endpush

    <div class="auth-wrapper {{ $isAgentPortal ? '' : 'auth-login-only' }}">
        <div class="bg-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
        </div>

        <div class="auth-card-container">
            @if ($isAgentPortal)
                <div class="auth-card">
                    <div class="auth-brand-side d-none d-md-block">
                        <div class="brand-content">
                            <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="brand-logo"
                                data-brand-logo="{{ $brandLogo }}">
                            <p class="brand-subtitle" style="font-weight: bold;">Portal Mitra Agen</p>

                            <x-illustration-agent-package class="agent-illustration" />

                            <div class="brand-features">
                                <div class="feature-item">
                                    <i class="ti ti-package"></i>
                                    <span>Pantau status pesanan real-time</span>
                                </div>
                                <div class="feature-item">
                                    <i class="ti ti-shield-check"></i>
                                    <span>Transaksi aman &amp; terenkripsi</span>
                                </div>
                                <div class="feature-item">
                                    <i class="ti ti-truck-delivery"></i>
                                    <span>Info pengiriman terkini</span>
                                </div>
                                <div class="feature-item">
                                    <i class="ti ti-bolt"></i>
                                    <span>Proses order lebih cepat</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="auth-form-side">
                        <div class="login-card">
                            <div class="auth-mobile-brand d-md-none">
                                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="brand-logo brand-logo-sm"
                                    data-brand-logo="{{ $brandLogo }}">
                                <p class="brand-subtitle">Portal Mitra Agen</p>
                            </div>

                            @include('customer.auth.partials.login-form')
                        </div>
                    </div>
                </div>
            @else
                <div class="login-card">
                    @include('customer.auth.partials.login-form')
                </div>
            @endif
        </div>
    </div>

    @push('page-js')
        <script>
            document.getElementById('toggle-password')?.addEventListener('click', function () {
                const input = document.getElementById('password');
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('ti-eye-off', 'ti-eye');
                } else {
                    input.type = 'password';
                    icon.classList.replace('ti-eye', 'ti-eye-off');
                }
            });

            document.getElementById('agent-customer-login-form')?.addEventListener('submit', function () {
                const btn = document.getElementById('login-submit-btn');
                if (btn) {
                    btn.disabled = true;
                }
            });
        </script>
    @endpush
</x-guest-layout>
