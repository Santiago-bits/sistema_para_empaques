<?php

use App\Http\Controllers\Dtv\DtvController;
use App\Http\Controllers\Dtv\TreatmentController;
use Illuminate\Support\Facades\Route;

/*
| DTV-e (SENASA) y tratamientos de la fruta: lo que el galpón llevaba en las hojas «DTV-e» y «TRATAMIENTO» del Excel.
*/

Route::middleware('module:loads')->group(function () {
    Route::prefix('dtv')->name('dtv.')->group(function () {
        Route::get('/', [DtvController::class, 'index'])->middleware('can:dtv.view')->name('index');
        Route::get('nuevo', [DtvController::class, 'create'])->middleware('can:dtv.manage')->name('create');
        Route::post('/', [DtvController::class, 'store'])->middleware('can:dtv.manage')->name('store');
        Route::post('importar', [DtvController::class, 'import'])->middleware(['can:dtv.manage', 'throttle:10,1'])->name('import');
        Route::get('{dtv}', [DtvController::class, 'show'])->whereNumber('dtv')->middleware('can:dtv.view')->name('show');
        Route::get('{dtv}/editar', [DtvController::class, 'edit'])->whereNumber('dtv')->middleware('can:dtv.manage')->name('edit');
        Route::put('{dtv}', [DtvController::class, 'update'])->whereNumber('dtv')->middleware('can:dtv.manage')->name('update');
        Route::post('{dtv}/eliminar', [DtvController::class, 'destroy'])->whereNumber('dtv')->middleware('can:dtv.manage')->name('destroy');
    });

    Route::prefix('tratamientos')->name('treatments.')->group(function () {
        Route::get('/', [TreatmentController::class, 'index'])->middleware('can:treatments.view')->name('index');
        Route::get('nuevo', [TreatmentController::class, 'create'])->middleware('can:treatments.manage')->name('create');
        Route::post('/', [TreatmentController::class, 'store'])->middleware('can:treatments.manage')->name('store');
        Route::get('{treatment}/editar', [TreatmentController::class, 'edit'])->whereNumber('treatment')->middleware('can:treatments.manage')->name('edit');
        Route::put('{treatment}', [TreatmentController::class, 'update'])->whereNumber('treatment')->middleware('can:treatments.manage')->name('update');
        Route::post('{treatment}/eliminar', [TreatmentController::class, 'destroy'])->whereNumber('treatment')->middleware('can:treatments.manage')->name('destroy');
    });
});
