<?php

use App\Http\Controllers\Admin\BomController;
use App\Http\Controllers\Admin\HppReportController;
use App\Http\Controllers\Admin\InboundController;
use App\Http\Controllers\Admin\ProductionOrderController;
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
        Route::get('/output-units', [BomController::class, 'outputUnits'])->name('bom.output-units')->middleware('permission:Bill of Materials,is_create');
        Route::get('/calculate', [BomController::class, 'calculate'])->name('bom.calculate')->middleware('permission:Bill of Materials,is_create');
        Route::post('/', [BomController::class, 'store'])->name('bom.store')->middleware('permission:Bill of Materials,is_create');
        Route::get('/{id}', [BomController::class, 'show'])->name('bom.show')->middleware('permission:Bill of Materials,is_read');
        Route::post('/{id}/delete', [BomController::class, 'destroy'])->name('bom.destroy')->middleware('permission:Bill of Materials,is_delete');
    });

    // --- Production Order ---
    Route::group(['prefix' => 'production'], function () {
        Route::get('/', [ProductionOrderController::class, 'index'])->name('production.index')->middleware('permission:Production Order,is_read');
        Route::get('/create', [ProductionOrderController::class, 'create'])->name('production.create')->middleware('permission:Production Order,is_create');
        Route::get('/bom-preview', [ProductionOrderController::class, 'bomPreview'])->name('production.bom-preview')->middleware('permission:Production Order,is_create');
        Route::get('/simulate', [ProductionOrderController::class, 'simulate'])->name('production.simulate')->middleware('permission:Production Order,is_create');
        Route::post('/', [ProductionOrderController::class, 'store'])->name('production.store')->middleware('permission:Production Order,is_create');
        Route::get('/{id}', [ProductionOrderController::class, 'show'])->name('production.show')->middleware('permission:Production Order,is_read');
        Route::post('/{id}/complete', [ProductionOrderController::class, 'complete'])->name('production.complete')->middleware('permission:Production Order,is_update');
    });

    // --- Replenishment (Distributor <-> Agen) ---
    Route::group(['prefix' => 'replenishment'], function () {
        Route::get('/', [ReplenishmentOrderController::class, 'index'])->name('replenishment.index')->middleware('permission:Replenishment,is_read');
        Route::get('/create', [ReplenishmentOrderController::class, 'create'])->name('replenishment.create')->middleware('permission:Replenishment,is_create');
        Route::post('/', [ReplenishmentOrderController::class, 'store'])->name('replenishment.store')->middleware('permission:Replenishment,is_create');
        Route::get('/{id}', [ReplenishmentOrderController::class, 'show'])->name('replenishment.show')->middleware('permission:Replenishment,is_read');
        Route::post('/{id}/approve', [ReplenishmentOrderController::class, 'approve'])->name('replenishment.approve')->middleware('permission:Replenishment,is_update');
        Route::post('/{id}/ship', [ReplenishmentOrderController::class, 'ship'])->name('replenishment.ship')->middleware('permission:Replenishment,is_update');
        Route::post('/{id}/receive/{shipmentId}', [ReplenishmentOrderController::class, 'receive'])->name('replenishment.receive')->middleware('permission:Replenishment,is_update');
        Route::post('/{id}/return', [ReplenishmentOrderController::class, 'returnGoods'])->name('replenishment.return')->middleware('permission:Replenishment,is_update');
    });

    // --- Laporan HPP ---
    Route::get('/laporan-hpp', [HppReportController::class, 'index'])->name('hpp.index')->middleware('permission:Laporan HPP,is_read');
});
