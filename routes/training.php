<?php

use App\Http\Controllers\Admin\Training\CategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Training Academy — management (/training) + learner (/academy)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // === Management (Administrator / Marketing) ===
    Route::prefix('training')->name('training.')->group(function () {
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index')->middleware('permission:Training Academy,is_read');
            Route::post('/', [CategoryController::class, 'store'])->name('store')->middleware('permission:Training Academy,is_create');
            Route::put('/{id}', [CategoryController::class, 'update'])->name('update')->middleware('permission:Training Academy,is_update');
            Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('destroy')->middleware('permission:Training Academy,is_delete');
        });
    });

});
