<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if (! $customer) {
            return redirect()->guest(route('agent-order.login'));
        }

        if (! $customer->isPartnerAgent()) {
            abort(403, 'Halaman ini khusus untuk agen.');
        }

        return $next($request);
    }
}
