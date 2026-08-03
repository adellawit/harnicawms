<?php

use App\Http\Controllers\Admin\Marketing\AssetController;
use App\Http\Controllers\Admin\Marketing\CampaignController;
use App\Http\Controllers\Admin\Marketing\CategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing Center — admin/marketing only (guard web)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->prefix('marketing')->name('marketing.')->group(function () {
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index')->middleware('permission:Marketing Center,is_read');
        Route::post('/', [CategoryController::class, 'store'])->name('store')->middleware('permission:Marketing Center,is_create');
        Route::put('/{id}', [CategoryController::class, 'update'])->name('update')->middleware('permission:Marketing Center,is_update');
        Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('destroy')->middleware('permission:Marketing Center,is_delete');
    });

    Route::prefix('assets')->name('assets.')->group(function () {
        Route::get('/', [AssetController::class, 'index'])->name('index')->middleware('permission:Marketing Center,is_read');
        Route::get('/create', [AssetController::class, 'create'])->name('create')->middleware('permission:Marketing Center,is_create');
        Route::post('/', [AssetController::class, 'store'])->name('store')->middleware('permission:Marketing Center,is_create');
        Route::get('/picker', [AssetController::class, 'picker'])->name('picker')->middleware('permission:Marketing Center,is_read');
        Route::get('/{id}/edit', [AssetController::class, 'edit'])->name('edit')->middleware('permission:Marketing Center,is_update');
        Route::put('/{id}', [AssetController::class, 'update'])->name('update')->middleware('permission:Marketing Center,is_update');
        Route::delete('/{id}', [AssetController::class, 'destroy'])->name('destroy')->middleware('permission:Marketing Center,is_delete');
    });

    Route::prefix('campaigns')->name('campaigns.')->group(function () {
        Route::get('/', [CampaignController::class, 'index'])->name('index')->middleware('permission:Marketing Campaign,is_read');
        Route::get('/create', [CampaignController::class, 'create'])->name('create')->middleware('permission:Marketing Campaign,is_create');
        Route::post('/', [CampaignController::class, 'store'])->name('store')->middleware('permission:Marketing Campaign,is_create');
        Route::get('/{id}/edit', [CampaignController::class, 'edit'])->name('edit')->middleware('permission:Marketing Campaign,is_update');
        Route::put('/{id}', [CampaignController::class, 'update'])->name('update')->middleware('permission:Marketing Campaign,is_update');
        Route::delete('/{id}', [CampaignController::class, 'destroy'])->name('destroy')->middleware('permission:Marketing Campaign,is_delete');
    });
});
