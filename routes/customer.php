<?php

use App\Http\Controllers\Customer\CustomerAuthController;
use App\Http\Controllers\Customer\ShopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer app (web orders) — terpisah dari admin WMS
|--------------------------------------------------------------------------
*/

Route::prefix('shop')->name('customer.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('/login', [CustomerAuthController::class, 'create'])->name('login');
        Route::post('/login', [CustomerAuthController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:customer')->group(function () {
        Route::get('/', [ShopController::class, 'index'])->name('shop');
        Route::get('/products/variants', [ShopController::class, 'productVariants'])->name('products.variants');
        Route::post('/cart/add', [ShopController::class, 'cartAdd'])->name('cart.add');
        Route::post('/cart/update', [ShopController::class, 'cartUpdate'])->name('cart.update');
        Route::post('/cart/remove', [ShopController::class, 'cartRemove'])->name('cart.remove');
        Route::get('/checkout', [ShopController::class, 'checkout'])->name('checkout');
        Route::post('/checkout', [ShopController::class, 'checkoutProcess'])->name('checkout.process');
        Route::get('/payment/return', [ShopController::class, 'paymentReturn'])->name('payment.return');
        Route::get('/payment/{orderId}/status', [ShopController::class, 'paymentStatus'])->name('payment.status');
        Route::get('/account', [CustomerAuthController::class, 'account'])->name('account');
        Route::post('/logout', [CustomerAuthController::class, 'destroy'])->name('logout');
    });
});

Route::prefix('orders')->name('customer.')->middleware('auth:customer')->group(function () {
    Route::get('/', [ShopController::class, 'orders'])->name('orders');
    Route::post('/{order}/cancel', [ShopController::class, 'cancelOrder'])->name('orders.cancel');
    Route::get('/{order}', [ShopController::class, 'orderShow'])->name('orders.show');
});
