<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force HTTPS if request is secure or from production domain
        $host = $request->getHost();
        
        if (str_contains($host, 'gateway.wit.id') || 
            $request->isSecure() ||
            $request->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
        
        return $next($request);
    }
}

