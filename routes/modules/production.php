<?php

use App\Http\Controllers\Crates\CrateController;
use App\Http\Controllers\Crates\LabelController;
use App\Http\Controllers\Crates\TraceabilityController;
use App\Http\Controllers\Pallets\PalletController;
use App\Http\Controllers\Production\ProductionRecordController;
use App\Http\Controllers\Production\ScanController;
use App\Http\Controllers\Production\StoppageController;
use Illuminate\Support\Facades\Route;

/*
| Ingreso, cajones, modo escaneo, producción y trazabilidad (Fases 3 y 4).
*/

// Pallets
Route::prefix('pallets')->name('pallets.')->middleware('module:pallets')->controller(PalletController::class)->group(function () {
    Route::get('/', 'index')->middleware('can:pallets.view')->name('index');
    Route::get('nuevo', 'create')->middleware('can:pallets.create')->name('create');
    Route::post('/', 'store')->middleware('can:pallets.create')->name('store');
    Route::get('{pallet}', 'show')->middleware('can:pallets.view')->name('show');
    Route::get('{pallet}/editar', 'edit')->middleware('can:pallets.update')->name('edit');
    Route::put('{pallet}', 'update')->middleware('can:pallets.update')->name('update');
    Route::post('{pallet}/anular', 'void')->middleware('can:pallets.void')->name('void');
});
Route::get('etiquetas/pallets', [LabelController::class, 'pallets'])->middleware(['module:pallets', 'can:labels.print'])->name('labels.pallets');

// Cajones, etiquetas y trazabilidad
Route::middleware('module:crates')->group(function () {
    Route::prefix('cajones')->name('crates.')->controller(CrateController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:crates.view')->name('index');
        Route::get('nuevo', 'create')->middleware('can:crates.create')->name('create');
        Route::post('/', 'store')->middleware('can:crates.create')->name('store');
        Route::get('{crate}', 'show')->middleware('can:crates.view')->name('show');
        Route::get('{crate}/editar', 'edit')->middleware('can:crates.update')->name('edit');
        Route::put('{crate}', 'update')->middleware('can:crates.update')->name('update');
        Route::post('{crate}/anular', 'void')->middleware('can:crates.void')->name('void');
    });

    Route::middleware('can:labels.print')->group(function () {
        Route::get('etiquetas/cajones', [LabelController::class, 'crates'])->name('labels.crates');
        Route::post('etiquetas/cajones/generar', [LabelController::class, 'generate'])->middleware('can:crates.create')->name('labels.generate');
    });

    Route::get('trazabilidad', [TraceabilityController::class, 'index'])->middleware('can:traceability.view')->name('traceability.index');
});

// Producción
Route::middleware('module:production')->group(function () {
    Route::middleware(['can:production.scan', 'lan:production'])->group(function () {
        Route::get('produccion/escaneo', [ScanController::class, 'show'])->name('production.scan');
        Route::get('kiosco', [ScanController::class, 'kiosk'])->name('kiosk');
        Route::post('produccion/escaneo', [ScanController::class, 'store'])->middleware('throttle:scan')->name('production.scan.store');
        Route::get('produccion/escaneo/validar', [ScanController::class, 'lookup'])->middleware('throttle:scan')->name('production.scan.lookup');
        Route::get('produccion/escaneo/balanza', [ScanController::class, 'scale'])->name('production.scan.scale');
    });

    Route::get('produccion/registros', [ProductionRecordController::class, 'index'])->middleware('can:production.view')->name('production.index');
    Route::post('produccion/registros/{record}/anular', [ProductionRecordController::class, 'void'])->middleware('can:production.void')->name('production.void');
    Route::get('mi-produccion', [ProductionRecordController::class, 'mine'])->middleware('can:production.own')->name('production.mine');

    Route::prefix('paradas')->name('stoppages.')->middleware('can:stoppages.manage')->controller(StoppageController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{stoppage}', 'update')->name('update');
    });
});
