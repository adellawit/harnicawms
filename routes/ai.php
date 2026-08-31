<?php

use App\Http\Controllers\Ai\ChatController;
use Illuminate\Support\Facades\Route;

/*
| Overlay Selesai / Lewati closes the product tour without posting "lanjut"
| through the LLM chat endpoint.
*/
Route::group([
    'prefix' => 'agent',
    'middleware' => [
        'permission:'.(config('agent.permission_menu') ?: 'AI Assistant').',is_read',
        'throttle:'.(int) config('agent.rate_limit_per_minute', 30).',1',
    ],
], function () {
    Route::post('/tour/stop', [ChatController::class, 'stopTour'])->name('agent.tour.stop');
});
