<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\PickingController;
use Modules\Inventory\Http\Controllers\StockController;
use Modules\Inventory\Http\Controllers\WarehouseController;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('can:inventory.stock.view')->group(function () {
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('stock/summary', [StockController::class, 'summary'])->name('stock.summary');
        Route::get('locations', [WarehouseController::class, 'locations'])->name('locations.index');
    });

    Route::middleware('can:inventory.transfer.view')->group(function () {
        Route::get('transfers', [PickingController::class, 'index'])->name('transfers.index');
        Route::get('transfers/{picking}', [PickingController::class, 'show'])->name('transfers.show');
    });
    Route::middleware('can:inventory.transfer.manage')->group(function () {
        Route::post('transfers', [PickingController::class, 'store'])->name('transfers.store');
        Route::put('transfers/{picking}', [PickingController::class, 'update'])->name('transfers.update');
        Route::post('transfers/{picking}/validate', [PickingController::class, 'validateTransfer'])->name('transfers.validate');
        Route::post('transfers/{picking}/cancel', [PickingController::class, 'cancel'])->name('transfers.cancel');
    });

    Route::middleware('can:inventory.adjustment.manage')->group(function () {
        Route::get('adjustments', [StockController::class, 'adjustments'])->name('adjustments.index');
        Route::post('adjustments', [StockController::class, 'adjust'])->name('adjustments.store');
    });

    Route::middleware('can:inventory.warehouse.manage')->group(function () {
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::post('locations', [WarehouseController::class, 'storeLocation'])->name('locations.store');
    });
});
