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
    public function create(): View
    {
        return view('customer.auth.login');
    }

    public function store(CustomerLoginRequest $request): RedirectResponse
    {
        $email = strtolower(trim($request->validated('email')));
        $password = $request->validated('password');

        $customer = Customer::query()
            ->with('customerGroup')
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

        Auth::guard('customer')->login($customer, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('customer.shop'));
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
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
