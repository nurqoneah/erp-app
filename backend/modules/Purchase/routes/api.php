<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\PurchaseOrderController;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('can:purchase.order.view')->group(function () {
        Route::get('orders', [PurchaseOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [PurchaseOrderController::class, 'show'])->name('orders.show');
    });

    Route::middleware('can:purchase.order.manage')->group(function () {
        Route::post('orders', [PurchaseOrderController::class, 'store'])->name('orders.store');
        Route::put('orders/{order}', [PurchaseOrderController::class, 'update'])->name('orders.update');
        Route::delete('orders/{order}', [PurchaseOrderController::class, 'destroy'])->name('orders.destroy');
    });

    Route::middleware('can:purchase.order.confirm')->group(function () {
        Route::post('orders/{order}/confirm', [PurchaseOrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/set-to-draft', [PurchaseOrderController::class, 'setToDraft'])->name('orders.set-to-draft');
        Route::post('orders/{order}/bill', [PurchaseOrderController::class, 'createBill'])->name('orders.bill');
    });
});
