<x-guest-layout>
    @section('title', 'Customer Login | ')

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
    @endpush

    <div class="auth-wrapper auth-login-only">
        <div class="bg-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
        </div>

        <div class="auth-card-container">
            <div class="login-card">
                <div class="login-header">
                    <h3>Customer Sign in</h3>
                    <p>{{ config('shop.default_company_name', 'WWW') }}</p>
                </div>

                @error('email')
                    <div class="alert-modern alert-danger">
                        <i class="ti ti-alert-circle"></i>
                        <span>{{ $message }}</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @enderror

                @error('password')
                    <div class="alert-modern alert-danger">
                        <i class="ti ti-alert-circle"></i>
                        <span>{{ $message }}</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @enderror

                <form method="POST" action="{{ route('customer.login.store') }}">
                    @csrf

                    <div class="form-floating-custom">
                        <i class="ti ti-mail input-icon"></i>
                        <input type="email" class="form-control" id="email" name="email"
                            placeholder="Email" autofocus autocomplete="email"
                            value="{{ old('email') }}" required>
                    </div>

                    <div class="form-floating-custom">
                        <i class="ti ti-lock input-icon"></i>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle-btn" id="toggle-password">
                            <i class="ti ti-eye-off"></i>
                        </button>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1"
                            @checked(old('remember'))>
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn-login">
                        <span>Sign In</span>
                        <i class="ti ti-arrow-right"></i>
                    </button>
                </form>

                <p class="text-center text-muted small mt-4 mb-0">
                    Staff? <a href="{{ route('login') }}">Login admin</a>
                </p>
            </div>
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
        </script>
    @endpush
</x-guest-layout>
