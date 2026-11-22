<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoicing\Http\Controllers\InvoiceController;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('can:invoicing.invoice.view')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    });

    Route::middleware('can:invoicing.invoice.manage')->group(function () {
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('invoices/{invoice}/post', [InvoiceController::class, 'post'])->name('invoices.post');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/reset-to-draft', [InvoiceController::class, 'resetToDraft'])->name('invoices.reset');
    });

    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'registerPayment'])
        ->middleware('can:invoicing.payment.manage')
        ->name('invoices.payments.store');
});
