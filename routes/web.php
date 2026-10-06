<?php

use App\Http\Controllers\ClientInvoicePdfController;
use App\Http\Controllers\DocumentDownloadController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('client-invoices/{invoice}/pdf', ClientInvoicePdfController::class)->name('client-invoices.pdf');
    Route::get('documents/{document}/download', DocumentDownloadController::class)->name('documents.download');
});

require __DIR__.'/settings.php';
