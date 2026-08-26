<div class="login-header">
    <h3>{{ $loginTitle ?? 'Customer Sign in' }}</h3>
    <p>{{ $loginSubtitle ?? config('shop.default_company_name', 'WWW') }}</p>
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

<form method="POST" action="{{ $loginAction ?? route('customer.login.store', absolute: false) }}" id="agent-customer-login-form">
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

    <button type="submit" class="btn-login" id="login-submit-btn">
        <span>Sign In</span>
        <i class="ti ti-arrow-right"></i>
    </button>
</form>

@if (($portal ?? 'shop') !== 'agent')
<p class="text-center text-muted small mt-4 mb-0">
    Staff? <a href="{{ route('login') }}">Login admin</a>
</p>
@endif
