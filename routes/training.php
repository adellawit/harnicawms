<?php

use App\Http\Controllers\Academy\AcademyController;
use App\Http\Controllers\Admin\Training\CategoryController;
use App\Http\Controllers\Admin\Training\CourseContentController;
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

        Route::prefix('courses')->name('courses.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\Training\CourseController::class, 'index'])->name('index')->middleware('permission:Training Academy,is_read');
            Route::get('/create', [\App\Http\Controllers\Admin\Training\CourseController::class, 'create'])->name('create')->middleware('permission:Training Academy,is_create');
            Route::post('/', [\App\Http\Controllers\Admin\Training\CourseController::class, 'store'])->name('store')->middleware('permission:Training Academy,is_create');
            Route::get('/{id}/edit', [\App\Http\Controllers\Admin\Training\CourseController::class, 'edit'])->name('edit')->middleware('permission:Training Academy,is_update');
            Route::put('/{id}', [\App\Http\Controllers\Admin\Training\CourseController::class, 'update'])->name('update')->middleware('permission:Training Academy,is_update');
            Route::delete('/{id}', [\App\Http\Controllers\Admin\Training\CourseController::class, 'destroy'])->name('destroy')->middleware('permission:Training Academy,is_delete');
            Route::post('/{id}/publish', [\App\Http\Controllers\Admin\Training\CourseController::class, 'publish'])->name('publish')->middleware('permission:Training Academy,is_update');
        });

        Route::get('/courses/{course}/content', [CourseContentController::class, 'content'])->name('courses.content')->middleware('permission:Training Academy,is_read');

        Route::prefix('courses/{course}/modules')->name('modules.')->group(function () {
            Route::post('/', [CourseContentController::class, 'storeModule'])->name('store')->middleware('permission:Training Academy,is_create');
            Route::put('/{module}', [CourseContentController::class, 'updateModule'])->name('update')->middleware('permission:Training Academy,is_update');
            Route::delete('/{module}', [CourseContentController::class, 'destroyModule'])->name('destroy')->middleware('permission:Training Academy,is_delete');

            Route::post('/{module}/materials', [CourseContentController::class, 'storeMaterial'])->name('materials.store')->middleware('permission:Training Academy,is_create');
            Route::put('/{module}/materials/{material}', [CourseContentController::class, 'updateMaterial'])->name('materials.update')->middleware('permission:Training Academy,is_update');
            Route::delete('/{module}/materials/{material}', [CourseContentController::class, 'destroyMaterial'])->name('materials.destroy')->middleware('permission:Training Academy,is_delete');
        });
    });

    // === Learner (Agent) ===
    Route::prefix('academy')->name('academy.')->group(function () {
        Route::get('/', [AcademyController::class, 'dashboard'])->name('dashboard')->middleware('permission:Academy,is_read');
    });

});
