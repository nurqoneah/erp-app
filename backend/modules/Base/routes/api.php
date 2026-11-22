<?php

use Illuminate\Support\Facades\Route;
use Modules\Base\Http\Controllers\AuthController;
use Modules\Base\Http\Controllers\CompanyController;
use Modules\Base\Http\Controllers\ModuleController;
use Modules\Base\Http\Controllers\RoleController;
use Modules\Base\Http\Controllers\SequenceController;
use Modules\Base\Http\Controllers\UserController;

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // Modul
    Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
    Route::get('menu', [ModuleController::class, 'menu'])->name('menu');
    Route::post('modules/{module}/install', [ModuleController::class, 'install'])->name('modules.install');
    Route::post('modules/{module}/upgrade', [ModuleController::class, 'upgrade'])->name('modules.upgrade');
    Route::get('modules/{module}/uninstall', [ModuleController::class, 'uninstallPlan'])->name('modules.uninstall-plan');
    Route::post('modules/{module}/uninstall', [ModuleController::class, 'uninstall'])->name('modules.uninstall');

    // Pengguna
    Route::get('users', [UserController::class, 'index'])->middleware('can:base.user.view')->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('can:base.user.view')->name('users.show');
    Route::post('users', [UserController::class, 'store'])->middleware('can:base.user.manage')->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:base.user.manage')->name('users.update');

    // Role & hak akses
    Route::middleware('can:base.role.manage')->group(function () {
        Route::get('permissions', [RoleController::class, 'permissions'])->name('permissions.index');
        Route::apiResource('roles', RoleController::class);
    });
    Route::get('role-options', [RoleController::class, 'options'])
        ->middleware('can:base.user.manage')
        ->name('roles.options');

    // Pengaturan perusahaan & penomoran
    Route::get('company', [CompanyController::class, 'show'])->name('company.show');
    Route::middleware('can:base.setting.manage')->group(function () {
        Route::put('company', [CompanyController::class, 'update'])->name('company.update');
        Route::get('sequences', [SequenceController::class, 'index'])->name('sequences.index');
        Route::put('sequences/{sequence}', [SequenceController::class, 'update'])->name('sequences.update');
    });
});
