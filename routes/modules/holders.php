<?php

use App\Http\Controllers\Catalogs\HolderActivityController;
use Illuminate\Support\Facades\Route;

/*
| Ficha de movimientos de cada cliente, productor, camionero, transportista, proveedor y empleado:
| plata, bines y envases, fruta que ingresó y cargas. Registro de entregas y devoluciones de envases.
*/

Route::prefix('fichas/{type}/{holder}/movimientos')
    ->whereIn('type', array_keys(\App\Models\ContainerMovement::HOLDERS))->whereNumber('holder')
    ->middleware(['module:catalogs', 'can:containers.view'])
    ->controller(HolderActivityController::class)
    ->group(function () {
        Route::get('/', 'show')->name('holders.activity');
        Route::middleware('can:containers.manage')->group(function () {
            Route::post('envases', 'store')->middleware('throttle:60,1')->name('holders.containers.store');
            Route::post('envases/{movement}/anular', 'void')->whereNumber('movement')->name('holders.containers.void');
        });
    });
