<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/settings/company', [App\Http\Controllers\CompanySettingsController::class, 'edit'])->name('settings.company.edit');
    Route::put('/settings/company', [App\Http\Controllers\CompanySettingsController::class, 'update'])->name('settings.company.update');
    Route::post('/settings/company/override-decision', [App\Http\Controllers\CompanySettingsController::class, 'overrideDecision'])->name('settings.company.override-decision');

    Route::resource('bank-accounts', App\Http\Controllers\BankAccountController::class)->except(['show']);
    Route::post('/bank-accounts/{bank_account}/set-default', [App\Http\Controllers\BankAccountController::class, 'setDefault'])->name('bank-accounts.set-default');
    Route::post('/bank-accounts/{bank_account}/restore', [App\Http\Controllers\BankAccountController::class, 'restore'])->name('bank-accounts.restore');

    Route::resource('clients', App\Http\Controllers\ClientController::class);

    Route::get('/invoices/vat-preview', [App\Http\Controllers\InvoiceController::class, 'vatPreview'])->name('invoices.vat-preview');
    Route::resource('invoices', App\Http\Controllers\InvoiceController::class);
    Route::post('/invoices/{invoice}/issue', [App\Http\Controllers\InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('/invoices/{invoice}/mark-paid', [App\Http\Controllers\InvoiceController::class, 'markPaid'])->name('invoices.markPaid');
    Route::post('/invoices/{invoice}/status', [App\Http\Controllers\InvoiceController::class, 'changeStatus'])->name('invoices.changeStatus');
    Route::get('/invoices/{invoice}/pdf', [App\Http\Controllers\InvoiceController::class, 'pdf'])->name('invoices.pdf');
});

Route::get('/s/{public_id}', [App\Http\Controllers\PublicInvoiceController::class, 'show'])
    ->name('invoices.share');

require __DIR__.'/auth.php';
