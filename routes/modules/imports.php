<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

// Importación de datos (validar → vista previa → confirmar).
Route::prefix('importaciones')->name('imports.')->middleware('can:imports.manage')->controller(ImportController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('nueva', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('plantilla/{type}', 'template')->name('template');
    Route::get('{import}', 'show')->name('show');
    Route::post('{import}/confirmar', 'confirm')->name('confirm');
    Route::get('{import}/errores', 'errors')->name('errors');
    Route::post('{import}/descartar', 'discard')->name('discard');
});
