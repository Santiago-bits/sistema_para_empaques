<?php

use App\Http\Controllers\Locations\LocationController;
use App\Http\Controllers\Locations\MovementController;
use Illuminate\Support\Facades\Route;

/*
| Ubicaciones: inventario físico, mapa del galpón y movimientos de pallets/cajones.
*/

Route::middleware(['module:locations', 'can:locations.view'])->group(function () {
    Route::get('ubicaciones', [LocationController::class, 'index'])->name('locations.index');
    Route::get('ubicaciones/mapa', [LocationController::class, 'map'])->name('locations.map');
    Route::get('ubicaciones/movimientos', [MovementController::class, 'index'])->name('locations.movements');

    Route::middleware('can:locations.move')->group(function () {
        Route::get('ubicaciones/mover', [MovementController::class, 'create'])->name('locations.move.create');
        Route::get('ubicaciones/buscar', [MovementController::class, 'lookup'])->name('locations.lookup');
        Route::post('ubicaciones/mover', [MovementController::class, 'store'])->name('locations.move');
    });

    Route::middleware('can:locations.manage')->group(function () {
        Route::get('ubicaciones/nueva', [LocationController::class, 'create'])->name('locations.create');
        Route::post('ubicaciones', [LocationController::class, 'store'])->name('locations.store');
        Route::put('ubicaciones/mapa', [LocationController::class, 'updateMap'])->name('locations.map.update');
        Route::get('ubicaciones/{location}/editar', [LocationController::class, 'edit'])->whereNumber('location')->name('locations.edit');
        Route::put('ubicaciones/{location}', [LocationController::class, 'update'])->whereNumber('location')->name('locations.update');
    });

    Route::get('ubicaciones/{location}', [LocationController::class, 'show'])->whereNumber('location')->name('locations.show');
    Route::get('ubicaciones/{location}/contenido', [LocationController::class, 'content'])->whereNumber('location')->name('locations.content');
});
