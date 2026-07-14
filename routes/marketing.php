<?php

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
});
