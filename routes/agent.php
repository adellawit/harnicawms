<?php

use App\Http\Controllers\Agent\AgentOrderController;
use App\Http\Controllers\Agent\AgentPosController;
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
        Route::post('/pelatihan/materi/{material}/progress', [AgentOrderController::class, 'trainingSaveProgress'])->name('training.progress');
        Route::post('/pelatihan/materi/{material}/complete', [AgentOrderController::class, 'trainingCompleteMaterial'])->name('training.complete');
        Route::get('/materi', [AgentOrderController::class, 'materials'])->name('materials');
        Route::get('/pos', [AgentPosController::class, 'index'])->name('pos');
        Route::get('/pos/history', [AgentPosController::class, 'history'])->name('pos.history');
        Route::get('/pos/history/{order}', [AgentPosController::class, 'historyShow'])->name('pos.history.show');
        Route::get('/pos/history/{order}/invoice-pdf', [AgentPosController::class, 'invoicePdf'])->name('pos.history.invoice-pdf');
        Route::get('/pos/resellers-search', [AgentPosController::class, 'resellerSearch'])->name('pos.resellers-search');
        Route::get('/pos/product-variants', [AgentPosController::class, 'getProductVariants'])->name('pos.product-variants');
        Route::post('/pos/preview-promo', [AgentPosController::class, 'previewPromo'])->name('pos.preview-promo');
        Route::post('/pos/barcode-lookup', [AgentPosController::class, 'lookupBarcode'])->name('pos.barcode-lookup');
        Route::post('/pos/order', [AgentPosController::class, 'orderOnly'])->name('pos.order');
        Route::post('/pos/payment', [AgentPosController::class, 'processPayment'])->name('pos.payment');
        Route::post('/pos/payment/{order}/pay-pending', [AgentPosController::class, 'payPendingOrder'])->name('pos.pay-pending');
        Route::get('/pos/payment/{orderId}/status', [AgentPosController::class, 'paymentStatus'])->name('pos.payment.status');
        Route::get('/pos/payment/return', [AgentPosController::class, 'paymentReturn'])->name('pos.payment.return');
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
        Route::post('/orders/{order}/receive', [AgentOrderController::class, 'receiveOrder'])->name('orders.receive');
        Route::post('/orders/{order}/upload-proof', [AgentOrderController::class, 'uploadPaymentProof'])->name('orders.upload-proof');
        Route::get('/orders/{order}/po-pdf', [AgentOrderController::class, 'orderPoPdf'])->name('orders.po-pdf');
        Route::get('/orders/{order}/invoice-pdf', [AgentOrderController::class, 'orderInvoicePdf'])->name('orders.invoice-pdf');
        Route::get('/stok', [AgentOrderController::class, 'stock'])->name('stock');
        Route::post('/logout', [CustomerAuthController::class, 'destroy'])->name('logout');
    });
});
