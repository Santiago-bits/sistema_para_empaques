<?php

use App\Http\Controllers\Central\ClientController;
use App\Http\Controllers\Central\TicketController;
use Illuminate\Support\Facades\Route;

/*
| Panel General del proveedor (sólo con GALPON_CENTRAL_MODE=true y para el super administrador):
| instalaciones aparte (galpones con su propio sistema), su uso y sus pedidos de soporte.
*/

Route::prefix('panel-general')->name('central.')->middleware(['central', 'can:developer'])->group(function () {
    Route::get('instalaciones', [ClientController::class, 'index'])->name('clients.index');
    Route::get('instalaciones/nuevo', [ClientController::class, 'create'])->name('clients.create');
    Route::post('instalaciones', [ClientController::class, 'store'])->name('clients.store');
    Route::get('instalaciones/{license}', [ClientController::class, 'show'])->whereNumber('license')->name('clients.show');
    Route::get('instalaciones/{license}/editar', [ClientController::class, 'edit'])->whereNumber('license')->name('clients.edit');
    Route::put('instalaciones/{license}', [ClientController::class, 'update'])->whereNumber('license')->name('clients.update');

    // Direcciones viejas («clientes», «galpones»): siguen funcionando.
    Route::permanentRedirect('clientes', '/panel-general/instalaciones');
    Route::permanentRedirect('galpones', '/panel-general/instalaciones');
    Route::get('{old}/{path}', fn (string $old, string $path) => redirect('/panel-general/instalaciones/'.$path, 301))
        ->whereIn('old', ['clientes', 'galpones'])->where('path', '[0-9]+(/editar)?|nuevo');
    Route::get('soporte', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('soporte/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
    Route::post('soporte/{ticket}/responder', [TicketController::class, 'reply'])->whereNumber('ticket')->name('tickets.reply');
    Route::post('soporte/{ticket}/estado', [TicketController::class, 'status'])->whereNumber('ticket')->name('tickets.status');
});
