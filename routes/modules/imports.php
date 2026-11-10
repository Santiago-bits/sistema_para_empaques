<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

// Importación de datos (validar → vista previa → confirmar).
Route::prefix('importaciones')->name('imports.')->middleware('can:imports.manage')->controller(ImportController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('nueva', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('plantilla/{type}', 'template')->name('template');
    // Carpeta entera: cada planilla se detecta y va a su sección; nada se guarda hasta confirmar.
    Route::get('carpeta', 'folderCreate')->name('folder.create');
    Route::post('carpeta/archivo', 'folderUpload')->middleware('throttle:120,1')->name('folder.upload');
    Route::get('carpeta/{group}', 'folderShow')->whereUuid('group')->name('folder.show');
    Route::post('carpeta/{group}/confirmar', 'folderConfirm')->whereUuid('group')->name('folder.confirm');
    Route::post('carpeta/{group}/descartar', 'folderDiscard')->whereUuid('group')->name('folder.discard');
    Route::post('{import}/seccion', 'retype')->name('retype');
    Route::get('{import}', 'show')->name('show');
    Route::post('{import}/confirmar', 'confirm')->name('confirm');
    Route::get('{import}/errores', 'errors')->name('errors');
    Route::post('{import}/descartar', 'discard')->name('discard');
});
