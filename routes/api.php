<?php

use Illuminate\Support\Facades\Route;

/*
| API REST. Las rutas versionadas (/api/v1) se agregan en la Fase 12.
*/

// Latido sin sesión: las PCs del galpón lo consultan para detectar pérdida de conexión
// sin consumir los mensajes flash de la sesión.
Route::get('/heartbeat', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]))
    ->middleware('throttle:120,1')
    ->name('api.heartbeat');
