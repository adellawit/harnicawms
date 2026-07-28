<?php

use App\Http\Controllers\Admin\BomController;
use App\Http\Controllers\Admin\HppReportController;
use App\Http\Controllers\Admin\InboundController;
use App\Http\Controllers\Admin\MarketingAllocationController;
use App\Http\Controllers\Admin\ProductionOrderController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReplenishmentOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes: Produksi & Distribusi (Distributor -> Agen -> Reseller)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // --- Stok Masuk (Inbound bahan baku / barang) ---
    Route::group(['prefix' => 'inbound'], function () {
        Route::get('/', [InboundController::class, 'index'])->name('inbound.index')->middleware('permission:Stok Masuk,is_read');
        Route::get('/create', [InboundController::class, 'create'])->name('inbound.create')->middleware('permission:Stok Masuk,is_create');
        Route::post('/', [InboundController::class, 'store'])->name('inbound.store')->middleware('permission:Stok Masuk,is_create');
        Route::get('/transfer', [InboundController::class, 'transferCreate'])->name('inbound.transfer.create')->middleware('permission:Stok Masuk,is_update');
        Route::post('/transfer', [InboundController::class, 'transferStore'])->name('inbound.transfer.store')->middleware('permission:Stok Masuk,is_update');
    });

    // --- Bill of Materials (Resep) ---
    Route::group(['prefix' => 'bom'], function () {
        Route::get('/', [BomController::class, 'index'])->name('bom.index')->middleware('permission:Bill of Materials,is_read');
        Route::get('/create', [BomController::class, 'create'])->name('bom.create')->middleware('permission:Bill of Materials,is_create');
        Route::post('/', [BomController::class, 'store'])->name('bom.store')->middleware('permission:Bill of Materials,is_create');
        Route::get('/{id}', [BomController::class, 'show'])->name('bom.show')->middleware('permission:Bill of Materials,is_read');
        Route::get('/{id}/edit', [BomController::class, 'edit'])->name('bom.edit')->middleware('permission:Bill of Materials,is_update');
        Route::put('/{id}', [BomController::class, 'update'])->name('bom.update')->middleware('permission:Bill of Materials,is_update');
        Route::post('/{id}/delete', [BomController::class, 'destroy'])->name('bom.destroy')->middleware('permission:Bill of Materials,is_delete');
    });

    // --- Production Order ---
    Route::group(['prefix' => 'production'], function () {
        Route::get('/', [ProductionOrderController::class, 'index'])->name('production.index')->middleware('permission:Production Order,is_read');
        Route::get('/create', [ProductionOrderController::class, 'create'])->name('production.create')->middleware('permission:Production Order,is_create');
        Route::get('/bom-preview', [ProductionOrderController::class, 'bomPreview'])->name('production.bom-preview')->middleware('permission:Production Order,is_create');
        Route::get('/bom-for-product', [ProductionOrderController::class, 'bomForProduct'])->name('production.bom-for-product')->middleware('permission:Production Order,is_create');
        Route::post('/', [ProductionOrderController::class, 'store'])->name('production.store')->middleware('permission:Production Order,is_create');
        Route::get('/{id}/edit', [ProductionOrderController::class, 'edit'])->name('production.edit')->middleware('permission:Production Order,is_update');
        Route::put('/{id}', [ProductionOrderController::class, 'update'])->name('production.update')->middleware('permission:Production Order,is_update');
        Route::delete('/{id}', [ProductionOrderController::class, 'destroy'])->name('production.destroy')->middleware('permission:Production Order,is_delete');
        Route::get('/{id}', [ProductionOrderController::class, 'show'])->name('production.show')->middleware('permission:Production Order,is_read');
        Route::post('/{id}/start', [ProductionOrderController::class, 'start'])->name('production.start')->middleware('permission:Production Order,is_update');
        Route::post('/{id}/finish', [ProductionOrderController::class, 'finish'])->name('production.finish')->middleware('permission:Production Order,is_update');
        Route::get('/{id}/receive', [ProductionOrderController::class, 'receiveView'])->name('production.receive')->middleware('permission:Production Order,is_update');
        Route::post('/{id}/receive', [ProductionOrderController::class, 'receive'])->name('production.receive.store')->middleware('permission:Production Order,is_update');
        Route::get('/{id}/receive/print', [ProductionOrderController::class, 'receivePrint'])->name('production.receive.print')->middleware('permission:Production Order,is_update');
    });

    // --- Promotions (buy X get Y rules) ---
    Route::group(['prefix' => 'promotions'], function () {
        Route::get('/', [PromotionController::class, 'index'])->name('promotions.index')->middleware('permission:Promotions,is_read');
        Route::get('/create', [PromotionController::class, 'create'])->name('promotions.create')->middleware('permission:Promotions,is_create');
        Route::post('/', [PromotionController::class, 'store'])->name('promotions.store')->middleware('permission:Promotions,is_create');
        Route::get('/{id}', [PromotionController::class, 'show'])->name('promotions.show')->middleware('permission:Promotions,is_read');
        Route::get('/{id}/edit', [PromotionController::class, 'edit'])->name('promotions.edit')->middleware('permission:Promotions,is_update');
        Route::put('/{id}', [PromotionController::class, 'update'])->name('promotions.update')->middleware('permission:Promotions,is_update');
        Route::delete('/{id}', [PromotionController::class, 'destroy'])->name('promotions.destroy')->middleware('permission:Promotions,is_delete');
    });

    // --- Marketing Allocation (Product warehouse → Marketing warehouse) ---
    Route::group(['prefix' => 'marketing-allocation'], function () {
        Route::get('/', [MarketingAllocationController::class, 'index'])->name('marketing-allocation.index')->middleware('permission:Marketing Allocation,is_read');
        Route::get('/create', [MarketingAllocationController::class, 'create'])->name('marketing-allocation.create')->middleware('permission:Marketing Allocation,is_create');
        Route::post('/', [MarketingAllocationController::class, 'store'])->name('marketing-allocation.store')->middleware('permission:Marketing Allocation,is_create');
        Route::get('/{id}', [MarketingAllocationController::class, 'show'])->name('marketing-allocation.show')->middleware('permission:Marketing Allocation,is_read');
    });

    // --- Agen Order / Replenishment (Distributor <-> Agen) ---
    // Primary URL: /agen-order (legacy /replenishment redirects below)
    Route::group(['prefix' => 'agen-order'], function () {
        Route::get('/', [ReplenishmentOrderController::class, 'index'])->name('replenishment.index')->middleware('permission:Replenishment,is_read');
        Route::get('/create', [ReplenishmentOrderController::class, 'create'])->name('replenishment.create')->middleware('permission:Replenishment,is_create');
        Route::post('/', [ReplenishmentOrderController::class, 'store'])->name('replenishment.store')->middleware('permission:Replenishment,is_create');
        Route::get('/{id}', [ReplenishmentOrderController::class, 'show'])->name('replenishment.show')->middleware('permission:Replenishment,is_read');
        Route::post('/{id}/approve', [ReplenishmentOrderController::class, 'approve'])->name('replenishment.approve')->middleware('permission:Replenishment,is_update');
        Route::post('/{id}/ship', [ReplenishmentOrderController::class, 'ship'])->name('replenishment.ship')->middleware('permission:Replenishment,is_update');
        Route::post('/{id}/receive/{shipmentId}', [ReplenishmentOrderController::class, 'receive'])->name('replenishment.receive')->middleware('permission:Replenishment,is_update');
        Route::post('/{id}/return', [ReplenishmentOrderController::class, 'returnGoods'])->name('replenishment.return')->middleware('permission:Replenishment,is_update');
    });

    Route::permanentRedirect('replenishment', 'agen-order');
    Route::permanentRedirect('replenishment/{path}', 'agen-order/{path}')->where('path', '.*');

    // --- Laporan HPP ---
    Route::get('/laporan-hpp', [HppReportController::class, 'index'])->name('hpp.index')->middleware('permission:Laporan HPP,is_read');
});
