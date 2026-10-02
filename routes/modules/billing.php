<?php

use App\Http\Controllers\Billing\ArcaController;
use App\Http\Controllers\Billing\InvoiceController;
use Illuminate\Support\Facades\Route;

/*
| Facturación y ARCA (Fase 10). La lógica de ARCA está desacoplada (app/Services/Arca).
*/

Route::prefix('facturacion')->name('invoices.')->middleware('module:billing')->controller(InvoiceController::class)->group(function () {
    Route::get('/', 'index')->middleware('can:billing.view')->name('index');
    Route::get('nuevo', 'create')->middleware('can:billing.manage')->name('create');
    Route::post('/', 'store')->middleware('can:billing.manage')->name('store');
    Route::get('{invoice}', 'show')->middleware('can:billing.view')->name('show');
    Route::get('{invoice}/editar', 'edit')->middleware('can:billing.manage')->name('edit');
    Route::put('{invoice}', 'update')->middleware('can:billing.manage')->name('update');
    Route::get('{invoice}/pdf', 'pdf')->middleware('can:billing.view')->name('pdf');
    Route::post('{invoice}/enviar', 'submit')->middleware(['can:billing.manage', 'can:arca.manage', 'module:arca'])->name('submit');
    Route::post('{invoice}/verificar', 'reconcile')->middleware(['can:billing.manage', 'can:arca.manage', 'module:arca', 'throttle:10,1'])->name('reconcile');
    Route::post('{invoice}/anular', 'void')->middleware('can:billing.void')->name('void');
});

Route::prefix('arca')->name('arca.')->middleware(['module:arca', 'can:arca.manage'])->controller(ArcaController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('probar', 'test')->name('test');
});
