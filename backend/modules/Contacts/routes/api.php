<?php

use Illuminate\Support\Facades\Route;
use Modules\Contacts\Http\Controllers\PartnerController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('partners', [PartnerController::class, 'index'])->middleware('can:contacts.partner.view')->name('partners.index');
    Route::get('partners/{partner}', [PartnerController::class, 'show'])->middleware('can:contacts.partner.view')->name('partners.show');
    Route::post('partners', [PartnerController::class, 'store'])->middleware('can:contacts.partner.manage')->name('partners.store');
    Route::put('partners/{partner}', [PartnerController::class, 'update'])->middleware('can:contacts.partner.manage')->name('partners.update');
    Route::delete('partners/{partner}', [PartnerController::class, 'destroy'])->middleware('can:contacts.partner.manage')->name('partners.destroy');
});
