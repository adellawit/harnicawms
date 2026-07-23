<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerLoginRequest;
use App\Models\Customer;
use App\Services\Shop\ShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function create(Request $request): View
    {
        return view('customer.auth.login', $this->loginViewData($request));
    }

    public function store(CustomerLoginRequest $request): RedirectResponse
    {
        $portal = $this->loginPortal($request);
        $email = strtolower(trim($request->validated('email')));
        $password = $request->validated('password');

        $customer = Customer::query()
            ->with(['customerGroup', 'agent'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->where('has_app_access', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->first();

        if ($customer === null || $password !== (string) config('customer.temp_password')) {
            throw ValidationException::withMessages([
                'email' => __('Email atau password tidak valid.'),
            ]);
        }

        if ($portal === 'agent' && ! $customer->isPartnerAgent()) {
            throw ValidationException::withMessages([
                'email' => __('Akun ini bukan agen. Gunakan login toko customer.'),
            ]);
        }

        Auth::guard('customer')->login($customer, $request->boolean('remember'));

        $request->session()->regenerate();

        $defaultRedirect = $portal === 'agent'
            ? route('agent-order.dashboard')
            : route('customer.shop');

        return redirect()->intended($defaultRedirect);
    }

    public function account(): View
    {
        $customer = auth('customer')->user()->load('customerGroup');
        $ctx = new ShopContextService($customer);

        return view('customer.account.index', [
            'customer' => $customer,
            'companyName' => $ctx->companyName(),
            'branch' => $ctx->branch(),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $portal = $this->loginPortal($request);

        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route(
            $portal === 'agent' ? 'agent-order.login' : 'customer.login'
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function loginViewData(Request $request): array
    {
        $portal = $this->loginPortal($request);

        return [
            'portal' => $portal,
            'loginTitle' => $portal === 'agent' ? 'Agent Login' : 'Customer Sign in',
            'loginSubtitle' => $portal === 'agent'
                ? 'Order ke Distributor'
                : config('shop.default_company_name', 'WWW'),
            'loginAction' => $portal === 'agent'
                ? route('agent-order.login.store')
                : route('customer.login.store'),
        ];
    }

    protected function loginPortal(Request $request): string
    {
        return $request->routeIs('agent-order.login', 'agent-order.login.store', 'agent-order.logout')
            ? 'agent'
            : 'shop';
    }
}
