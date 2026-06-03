<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\ClientSubscribeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Generate Token API (Public - no authentication required)
Route::post('/generate-token', [ApiTokenController::class, 'generateToken']);

// Client Subscribe Status API (Protected - requires token)
Route::middleware('api.token')->group(function () {
    Route::post('/client/subscribe-status', [ClientSubscribeController::class, 'checkStatus']);
});