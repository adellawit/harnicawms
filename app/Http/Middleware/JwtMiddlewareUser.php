<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\ResponseApiController;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class JwtMiddlewareUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user || $user->deleted_at) {
                return ResponseApiController::getResponse(null, 401, 'User has been deleted, contact admin to restore');
            }

            if (!$user || ($user->role_id != '4075f58e-fdd1-44ef-90af-3c697abfb348')) {
                return ResponseApiController::getResponse(null, 401, "User doesn't have permission to access the endpoint");
            }
        } catch (Exception $e) {
            if ($e instanceof TokenInvalidException) {
                return ResponseApiController::getResponse(null, 401, 'Token is invalid');
            } else if ($e instanceof TokenExpiredException) {
                return ResponseApiController::getResponse(null, 401, 'Token is expired');
            } else {
                return ResponseApiController::getResponse(null, 401, 'Authorization token not found');
            }
        }

        return $next($request);
    }
}
