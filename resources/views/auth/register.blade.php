<x-guest-layout>
    @section('title', 'Register | ')

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
                                <i class="ti ti-user-plus"></i>
                                <span>Buat akun untuk akses dashboard</span>
                            </div>
                            <div class="feature-item">
                                <i class="ti ti-shield-lock"></i>
                                <span>Password terenkripsi &amp; aman</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="auth-form-side">
                    <div class="login-card">
                        <div class="login-header">
                            <h3>Daftar akun</h3>
                            <p>Lengkapi data di bawah untuk mendaftar</p>
                        </div>

                        @if ($errors->any())
                            <div class="alert-modern alert-danger">
                                <i class="ti ti-alert-circle"></i>
                                <span>{{ $errors->first() }}</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('register') }}">
                            @csrf

                            <div class="form-floating-custom">
                                <i class="ti ti-user input-icon"></i>
                                <input type="text" class="form-control" id="reg-name" name="name"
                                    placeholder="Nama lengkap" value="{{ old('name') }}" required autocomplete="name">
                            </div>
                            @error('name')
                                <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror

                            <div class="form-floating-custom">
                                <i class="ti ti-user-circle input-icon"></i>
                                <input type="text" class="form-control" id="reg-username" name="username"
                                    placeholder="Username" value="{{ old('username') }}" required autocomplete="username">
                            </div>
                            @error('username')
                                <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror

                            <div class="form-floating-custom">
                                <i class="ti ti-lock input-icon"></i>
                                <input type="password" class="form-control" id="reg-password" name="password"
                                    placeholder="Password" required autocomplete="new-password">
                            </div>
                            @error('password')
                                <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror

                            <div class="form-floating-custom">
                                <i class="ti ti-lock input-icon"></i>
                                <input type="password" class="form-control" id="reg-password-confirmation"
                                    name="password_confirmation" placeholder="Konfirmasi password" required autocomplete="new-password">
                            </div>

                            <button type="submit" class="btn-login">
                                <span>Register</span>
                                <i class="ti ti-arrow-right"></i>
                            </button>

                            <p class="text-center mt-3 mb-0">
                                <a href="{{ route('login') }}" class="forgot-password-link">Sudah punya akun? Sign in</a>
                            </p>
                            <p class="text-center mt-2 mb-0">
                                <a href="{{ route('login') }}" class="forgot-password-link">Kembali ke login</a>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
