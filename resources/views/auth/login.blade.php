<x-guest-layout>
    @section('title', 'Login | ')

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
                        <img src="{{ asset('assets/img/harnica/logo.png') }}" alt="Harnica" class="brand-logo">
                        <p class="brand-subtitle" style="font-weight: bold;">{{ config('app.name', 'WIT. Management System') }}</p>
                        <div class="brand-features">
                            <div class="feature-item">
                                <i class="ti ti-package"></i>
                                <span>Product &amp; Inventory Management</span>
                            </div>
                            <div class="feature-item">
                                <i class="ti ti-shopping-cart"></i>
                                <span>POS &amp; Sales Transaction</span>
                            </div>
                            <div class="feature-item">
                                <i class="ti ti-truck-delivery"></i>
                                <span>Purchasing &amp; Supplier</span>
                            </div>
                            <div class="feature-item">
                                <i class="ti ti-chart-bar"></i>
                                <span>Reporting &amp; Analytics</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="auth-form-side">
                    <div class="login-card">
                        <div class="auth-mobile-brand d-md-none">
                            <img src="{{ asset('assets/img/harnica/logo.png') }}" alt="Harnica" class="brand-logo brand-logo-sm">
                            <p class="brand-subtitle">{{ config('app.name', 'WIT. Management System') }}</p>
                        </div>

                        <div class="login-header">
                            <h3>Welcome! 👋</h3>
                            <p>Please sign in to your account to continue</p>
                        </div>

                        @error('username')
                            <div class="alert-modern alert-danger">
                                <i class="ti ti-alert-circle"></i>
                                <span>Email is required.</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @enderror

                        @error('password')
                            <div class="alert-modern alert-danger">
                                <i class="ti ti-alert-circle"></i>
                                <span>Password is required.</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @enderror

                        @error('email')
                            <div class="alert-modern alert-danger">
                                <i class="ti ti-alert-circle"></i>
                                <span>Invalid email or password</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @enderror

                        @if (session('error'))
                            <div class="alert-modern alert-danger">
                                <i class="ti ti-alert-circle"></i>
                                <span>{{ session('error') }}</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}">
                            @csrf

                            <div class="form-floating-custom">
                                <i class="ti ti-user input-icon"></i>
                                <input type="text" class="form-control" id="username" name="username"
                                    placeholder="Enter your email" autofocus autocomplete="username"
                                    value="{{ old('username') }}">
                            </div>

                            <div class="form-floating-custom">
                                <i class="ti ti-lock input-icon"></i>
                                <input type="password" class="form-control" id="password" name="password"
                                    placeholder="Enter your password" autocomplete="current-password">
                                <button type="button" class="password-toggle-btn" id="toggle-password">
                                    <i class="ti ti-eye-off"></i>
                                </button>
                            </div>

                            <button type="submit" class="btn-login">
                                <span>Sign In</span>
                                <i class="ti ti-arrow-right"></i>
                            </button>

                            <a href="#" class="forgot-password-link" data-bs-toggle="modal"
                                data-bs-target="#forgotPasswordModal">
                                Forgot password?
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade modal-modern" id="forgotPasswordModal" tabindex="-1"
        aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forgotPasswordModalLabel">Forgot Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-4 text-start">Enter your email address and we'll send you a link to reset your password.</p>

                    <!-- Success Message -->
                    @if (session('status'))
                        <div class="alert-modern alert-success mb-3">
                            <i class="ti ti-check-circle"></i>
                            <span>{{ session('status') }}</span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <!-- Error Messages -->
                    @error('email')
                        <div class="alert-modern alert-danger mb-3">
                            <i class="ti ti-alert-circle"></i>
                            <span>{{ $message }}</span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @enderror

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <!-- Email Input -->
                        <div class="form-floating-custom mb-3">
                            <i class="ti ti-mail input-icon"></i>
                            <input type="email" class="form-control" id="forgot-email" name="email"
                                placeholder="Enter your email" autofocus autocomplete="email"
                                value="{{ old('email') }}" required>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn-login w-100">
                            <span>Send Password Reset Link</span>
                            <i class="ti ti-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>

<script>
    document.getElementById('toggle-password').addEventListener('click', function () {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('ti-eye-off');
            icon.classList.add('ti-eye');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('ti-eye');
            icon.classList.add('ti-eye-off');
        }
    });

    // Add focus animation to form inputs
    document.querySelectorAll('.form-floating-custom .form-control').forEach(input => {
        input.addEventListener('focus', function () {
            this.parentElement.classList.add('focused');
        });
        input.addEventListener('blur', function () {
            this.parentElement.classList.remove('focused');
        });
    });
</script>

