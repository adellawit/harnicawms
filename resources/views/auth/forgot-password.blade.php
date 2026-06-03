<x-guest-layout>
    @section('title', 'Lupa password | ')

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
    @endpush

    <div class="auth-wrapper">
        <div class="bg-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
        </div>

        <div class="auth-card-container">
            <div class="auth-card">
                <div class="auth-brand-side d-none d-md-block">
                    <div class="brand-content">
                        <div class="brand-logo-card">A</div>
                        <p class="brand-subtitle" style="font-weight: bold;">WIT. Management System</p>
                        <div class="brand-features">
                            <div class="feature-item">
                                <i class="ti ti-mail"></i>
                                <span>Reset password lewat email terdaftar</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="auth-form-side">
                    <div class="login-card">
                        <div class="login-header">
                            <h3>Lupa password</h3>
                            <p>Masukkan email akun kamu untuk menerima tautan reset.</p>
                        </div>

                        @if (session('status'))
                            <div class="alert-modern alert-success mb-3">
                                <i class="ti ti-check-circle"></i>
                                <span>{{ session('status') }}</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert-modern alert-danger mb-3">
                                <i class="ti ti-alert-circle"></i>
                                <span>{{ $errors->first() }}</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('password.email') }}">
                            @csrf

                            <div class="form-floating-custom">
                                <i class="ti ti-mail input-icon"></i>
                                <input type="email" class="form-control" id="fp-email" name="email"
                                    placeholder="Email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                            </div>

                            <button type="submit" class="btn-login mt-3">
                                <span>Kirim tautan reset</span>
                                <i class="ti ti-arrow-right"></i>
                            </button>

                            <a href="{{ route('login') }}" class="forgot-password-link d-block text-center mt-3">
                                Kembali ke login
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
