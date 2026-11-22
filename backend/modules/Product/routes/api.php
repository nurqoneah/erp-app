<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\ProductCategoryController;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Http\Controllers\UomController;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('can:product.product.view')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('categories', [ProductCategoryController::class, 'index'])->name('categories.index');
        Route::get('uoms', [UomController::class, 'index'])->name('uoms.index');
    });

    Route::middleware('can:product.product.manage')->group(function () {
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::post('categories', [ProductCategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [ProductCategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [ProductCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::post('uom-categories', [UomController::class, 'storeCategory'])->name('uom-categories.store');
        Route::post('uoms', [UomController::class, 'store'])->name('uoms.store');
        Route::put('uoms/{uom}', [UomController::class, 'update'])->name('uoms.update');
    });
});
