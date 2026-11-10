<?php

use App\Http\Controllers\Central\ClientController;
use App\Http\Controllers\Central\TicketController;
use Illuminate\Support\Facades\Route;

/*
| Panel General del proveedor (sólo con GALPON_CENTRAL_MODE=true y para el super administrador):
| galpones (cada uno con su propio sistema), su uso y sus pedidos de soporte.
*/

Route::prefix('panel-general')->name('central.')->middleware(['central', 'can:developer'])->group(function () {
    Route::get('galpones', [ClientController::class, 'index'])->name('clients.index');
    Route::get('galpones/nuevo', [ClientController::class, 'create'])->name('clients.create');
    Route::post('galpones', [ClientController::class, 'store'])->name('clients.store');
    Route::get('galpones/{license}', [ClientController::class, 'show'])->whereNumber('license')->name('clients.show');
    Route::get('galpones/{license}/editar', [ClientController::class, 'edit'])->whereNumber('license')->name('clients.edit');
    Route::put('galpones/{license}', [ClientController::class, 'update'])->whereNumber('license')->name('clients.update');

    // Direcciones viejas (antes «clientes»): siguen funcionando.
    Route::permanentRedirect('clientes', '/panel-general/galpones');
    Route::get('clientes/{path}', fn (string $path) => redirect('/panel-general/galpones/'.$path, 301))->where('path', '[0-9]+(/editar)?|nuevo');
    Route::get('soporte', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('soporte/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
    Route::post('soporte/{ticket}/responder', [TicketController::class, 'reply'])->whereNumber('ticket')->name('tickets.reply');
    Route::post('soporte/{ticket}/estado', [TicketController::class, 'status'])->whereNumber('ticket')->name('tickets.status');
});
