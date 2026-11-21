<?php

use Illuminate\Support\Facades\Route;
use Modules\Base\Http\Controllers\AuthController;
use Modules\Base\Http\Controllers\ModuleController;

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
    Route::get('menu', [ModuleController::class, 'menu'])->name('menu');
    Route::post('modules/{module}/enable', [ModuleController::class, 'enable'])->name('modules.enable');
    Route::post('modules/{module}/disable', [ModuleController::class, 'disable'])->name('modules.disable');
});
