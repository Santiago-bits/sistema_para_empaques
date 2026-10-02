<?php

use App\Http\Controllers\Loads\DocumentController;
use App\Http\Controllers\Loads\LoadController;
use App\Http\Controllers\Loads\RemitoController;
use Illuminate\Support\Facades\Route;

/*
| Logística: cargas (armado concurrente, cierre, despacho), remitos y documentos (Fases 6 y 7).
*/

Route::prefix('cargas')->name('loads.')->middleware('module:loads')->controller(LoadController::class)->group(function () {
    Route::get('/', 'index')->middleware('can:loads.view')->name('index');
    Route::get('nueva', 'create')->middleware('can:loads.create')->name('create');
    Route::post('/', 'store')->middleware('can:loads.create')->name('store');
    Route::get('{load}', 'show')->middleware('can:loads.view')->name('show');
    Route::get('{load}/editar', 'edit')->middleware('can:loads.update')->name('edit');
    Route::put('{load}', 'update')->middleware('can:loads.update')->name('update');
    Route::get('{load}/corregir', 'correct')->middleware('can:loads.update')->name('correct');
    Route::put('{load}/corregir', 'saveCorrection')->middleware('can:loads.update')->name('correct.save');

    Route::middleware('can:loads.update')->group(function () {
        Route::get('{load}/armado', 'builder')->name('builder');
        Route::get('{load}/disponibles', 'available')->name('available');
        Route::get('{load}/contenido', 'content')->name('content');
        Route::post('{load}/cajones', 'assign')->middleware('throttle:scan')->name('crates.assign');
        Route::post('{load}/cajones/quitar', 'remove')->name('crates.remove');
    });

    Route::get('{load}/cierre', 'closeSummary')->middleware('can:loads.close')->name('close.show');
    Route::post('{load}/cierre', 'close')->middleware('can:loads.close')->name('close');
    Route::post('{load}/reabrir', 'reopen')->middleware('can:loads.reopen')->name('reopen');
    Route::post('{load}/cancelar', 'cancel')->middleware('can:loads.cancel')->name('cancel');

    Route::middleware('can:loads.dispatch')->group(function () {
        Route::get('{load}/despacho', 'dispatchForm')->name('dispatch.show');
        Route::post('{load}/despacho/control', 'check')->name('dispatch.check');
        Route::post('{load}/despacho', 'dispatch')->name('dispatch');
    });
});

Route::middleware('module:remitos')->group(function () {
    Route::post('cargas/{load}/remito', [RemitoController::class, 'store'])->middleware('can:remitos.create')->name('remitos.store');

    Route::prefix('remitos')->name('remitos.')->controller(RemitoController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:remitos.view')->name('index');
        Route::get('{remito}', 'show')->middleware('can:remitos.view')->name('show');
        Route::get('{remito}/pdf', 'pdf')->middleware('can:remitos.view')->name('pdf');
        Route::get('{remito}/firma', 'signature')->middleware('can:remitos.view')->name('signature');
        Route::get('{remito}/entrega', 'deliverForm')->middleware('can:remitos.deliver')->name('deliver.show');
        Route::post('{remito}/entrega', 'deliver')->middleware('can:remitos.deliver')->name('deliver');
        Route::post('{remito}/anular', 'void')->middleware('can:remitos.void')->name('void');
    });
});

Route::prefix('documentos')->name('documents.')->middleware('module:documents')->controller(DocumentController::class)->group(function () {
    Route::get('/', 'index')->middleware('can:documents.view')->name('index');
    Route::post('/', 'store')->middleware('can:documents.manage')->name('store');
    Route::get('{document}/descargar', 'download')->middleware('can:documents.view')->name('download');
    Route::delete('{document}', 'destroy')->middleware('can:documents.manage')->name('destroy');
});
