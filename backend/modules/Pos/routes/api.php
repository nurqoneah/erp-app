<?php

use Illuminate\Support\Facades\Route;
use Modules\Pos\Http\Controllers\PaymentMethodController;
use Modules\Pos\Http\Controllers\PosConfigController;
use Modules\Pos\Http\Controllers\PosOrderController;
use Modules\Pos\Http\Controllers\PosSessionController;

Route::middleware('auth:sanctum')->group(function () {
    // Kasir
    Route::middleware('can:pos.session.use')->group(function () {
        Route::get('configs', [PosConfigController::class, 'index'])->name('configs.index');
        Route::post('configs/{config}/open', [PosConfigController::class, 'open'])->name('configs.open');
        Route::post('sessions/{session}/opening', [PosSessionController::class, 'openingControl'])->name('sessions.opening');
        Route::get('sessions/{session}/load', [PosSessionController::class, 'load'])->name('sessions.load');
        Route::post('sessions/{session}/cash-moves', [PosSessionController::class, 'cashMove'])->name('sessions.cash-moves');
        Route::post('sessions/{session}/close', [PosSessionController::class, 'close'])->name('sessions.close');
        Route::post('sessions/{session}/orders', [PosOrderController::class, 'store'])->name('orders.store');
    });

    Route::middleware('can:pos.session.view')->group(function () {
        Route::get('sessions', [PosSessionController::class, 'index'])->name('sessions.index');
        Route::get('sessions/{session}', [PosSessionController::class, 'show'])->name('sessions.show');
    });

    Route::middleware('can:pos.order.view')->group(function () {
        Route::get('orders', [PosOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [PosOrderController::class, 'show'])->name('orders.show');
    });

    // Manajer POS
    Route::middleware('can:pos.config.manage')->group(function () {
        Route::get('configs/{config}', [PosConfigController::class, 'show'])->name('configs.show');
        Route::post('configs', [PosConfigController::class, 'store'])->name('configs.store');
        Route::put('configs/{config}', [PosConfigController::class, 'update'])->name('configs.update');
        Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    });
});
