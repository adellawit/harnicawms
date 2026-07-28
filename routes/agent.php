<?php

use App\Http\Controllers\Agent\AgentOrderController;
use App\Http\Controllers\Customer\CustomerAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent order landing — order agent → distributor (guard customer + agent gate)
|--------------------------------------------------------------------------
|
| Prefix /agent-order (bukan /agen-order) agar tidak bentrok dengan admin
| Replenishment di /agen-order.
|
*/

 // Legacy bookmark: /agen-order/login dulu portal agent, sekarang tertangkap admin {id}.
Route::permanentRedirect('agen-order/login', '/agent-order/login');
Route::permanentRedirect('agen-order/checkout', '/agent-order/checkout');
Route::permanentRedirect('agen-order/orders', '/agent-order/orders');

Route::prefix('agent-order')->name('agent-order.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('/login', [CustomerAuthController::class, 'create'])->name('login');
        Route::post('/login', [CustomerAuthController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth:customer', 'agent'])->group(function () {
        Route::get('/', [AgentOrderController::class, 'index'])->name('index');
        Route::get('/beranda', [AgentOrderController::class, 'dashboard'])->name('dashboard');
        Route::get('/reseller', [AgentOrderController::class, 'resellers'])->name('resellers');
        Route::get('/pelatihan', [AgentOrderController::class, 'training'])->name('training');
        Route::get('/pelatihan/{course}', [AgentOrderController::class, 'trainingShow'])->name('training.show');
        Route::get('/materi', [AgentOrderController::class, 'materials'])->name('materials');
        Route::post('/reorder/{order}', [AgentOrderController::class, 'reorder'])->name('reorder');
        Route::get('/products/variants', [AgentOrderController::class, 'productVariants'])->name('products.variants');
        Route::post('/cart/add', [AgentOrderController::class, 'cartAdd'])->name('cart.add');
        Route::post('/cart/update', [AgentOrderController::class, 'cartUpdate'])->name('cart.update');
        Route::post('/cart/remove', [AgentOrderController::class, 'cartRemove'])->name('cart.remove');
        Route::get('/checkout', [AgentOrderController::class, 'checkout'])->name('checkout');
        Route::post('/checkout', [AgentOrderController::class, 'checkoutProcess'])->name('checkout.process');
        Route::get('/payment/return', [AgentOrderController::class, 'paymentReturn'])->name('payment.return');
        Route::get('/payment/{orderId}/status', [AgentOrderController::class, 'paymentStatus'])->name('payment.status');
        Route::get('/orders', [AgentOrderController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [AgentOrderController::class, 'orderShow'])->name('orders.show');
        Route::post('/logout', [CustomerAuthController::class, 'destroy'])->name('logout');
    });
});
