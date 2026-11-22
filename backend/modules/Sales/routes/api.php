<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesOrderController;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('can:sales.order.view')->group(function () {
        Route::get('orders', [SalesOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [SalesOrderController::class, 'show'])->name('orders.show');
    });

    Route::middleware('can:sales.order.manage')->group(function () {
        Route::post('orders', [SalesOrderController::class, 'store'])->name('orders.store');
        Route::put('orders/{order}', [SalesOrderController::class, 'update'])->name('orders.update');
        Route::delete('orders/{order}', [SalesOrderController::class, 'destroy'])->name('orders.destroy');
    });

    Route::middleware('can:sales.order.confirm')->group(function () {
        Route::post('orders/{order}/confirm', [SalesOrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/cancel', [SalesOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/set-to-draft', [SalesOrderController::class, 'setToDraft'])->name('orders.set-to-draft');
        Route::post('orders/{order}/invoice', [SalesOrderController::class, 'createInvoice'])->name('orders.invoice');
    });
});
