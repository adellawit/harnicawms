<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\ResponseApiController;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('Authorization');

        // Remove 'Bearer ' prefix if present
        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if (!$token) {
            return ResponseApiController::getResponse(null, 401, 'Authorization token not found');
        }

        $apiToken = ApiToken::where('token', $token)->first();

        if (!$apiToken) {
            return ResponseApiController::getResponse(null, 401, 'Invalid token');
        }

        if (!$apiToken->isValid()) {
            return ResponseApiController::getResponse(null, 401, 'Token is expired or inactive');
        }

        // Attach token to request for use in controllers
        $request->merge(['api_token' => $apiToken]);

        return $next($request);
    }
}

