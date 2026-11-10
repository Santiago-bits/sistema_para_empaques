<?php

use App\Http\Controllers\SuperAdmin\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
| Multigalpón: el Super Administrador elige en qué galpón trabajar (selector del encabezado).
| No pide volver a escribir la contraseña: sólo cambia de galpón; el controlador rechaza a cualquier otro usuario.
*/
Route::post('galpones/{warehouse}/entrar', [WarehouseController::class, 'enter'])->whereNumber('warehouse')->name('warehouses.enter');
