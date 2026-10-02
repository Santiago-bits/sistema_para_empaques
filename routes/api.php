<?php

use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ReadController;
use Illuminate\Support\Facades\Route;

/*
| API REST. Siempre JSON. Autenticación por token (Sanctum, encabezado «Authorization: Bearer …»).
| Cada token tiene habilidades (read, scale:write, sensors:write) y además rigen los permisos
| y módulos del usuario dueño del token. Documentación: docs/API.md.
*/

// Latido sin sesión: las PCs del galpón lo consultan para detectar pérdida de conexión
// sin consumir los mensajes flash de la sesión.
Route::get('/heartbeat', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]))
    ->middleware('throttle:120,1')
    ->name('api.heartbeat');

// Panel General (sólo en el servidor del proveedor): los empaques reportan uso y soporte con su licencia.
Route::prefix('central/v1')->name('api.central.')->middleware(['central', 'throttle:60,1', 'installation'])
    ->controller(\App\Http\Controllers\Api\Central\InstallationController::class)->group(function () {
        Route::post('reportes', 'report')->name('report');
        Route::post('tickets', 'ticket')->name('ticket');
        Route::get('novedades', 'updates')->name('updates');
        Route::post('novedades/recibidas', 'acknowledge')->name('acknowledge');
    });

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
    Route::get('me', [ReadController::class, 'me'])->name('me');

    Route::middleware('abilities:read')->group(function () {
        Route::get('cajones', [ReadController::class, 'crates'])->middleware('module:crates')->name('crates.index');
        Route::get('cajones/{code}', [ReadController::class, 'crateShow'])->middleware('module:crates')->where('code', '[A-Za-z0-9\-_.]+')->name('crates.show');
        Route::get('pallets/{code}', [ReadController::class, 'pallet'])->middleware('module:pallets')->where('code', '[A-Za-z0-9\-_.]+')->name('pallets.show');
        Route::get('cargas', [ReadController::class, 'loads'])->middleware('module:loads')->name('loads.index');
        Route::get('cargas/{number}', [ReadController::class, 'loadShow'])->middleware('module:loads')->where('number', '[A-Za-z0-9\-_.]+')->name('loads.show');
        Route::get('insumos', [ReadController::class, 'supplies'])->middleware('module:supplies')->name('supplies.index');
        Route::get('produccion/hoy', [ReadController::class, 'today'])->name('production.today');
    });

    Route::post('balanza/lecturas', [DeviceController::class, 'scale'])
        ->middleware(['abilities:scale:write', 'module:production', 'throttle:scan'])->name('scale.readings');
    Route::post('sensores/lecturas', [DeviceController::class, 'sensor'])
        ->middleware(['abilities:sensors:write', 'module:cold_rooms'])->name('sensors.readings');
});
