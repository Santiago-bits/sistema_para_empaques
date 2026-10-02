<?php

use App\Http\Controllers\Central\ClientController;
use App\Http\Controllers\Central\TicketController;
use Illuminate\Support\Facades\Route;

/*
| Panel General del proveedor (sólo con GALPON_CENTRAL_MODE=true y para el super administrador):
| clientes (empaques), su uso del sistema y sus pedidos de soporte.
*/

Route::prefix('panel-general')->name('central.')->middleware(['central', 'can:developer'])->group(function () {
    Route::get('clientes', [ClientController::class, 'index'])->name('clients.index');
    Route::get('clientes/nuevo', [ClientController::class, 'create'])->name('clients.create');
    Route::post('clientes', [ClientController::class, 'store'])->name('clients.store');
    Route::get('clientes/{license}', [ClientController::class, 'show'])->whereNumber('license')->name('clients.show');
    Route::get('clientes/{license}/editar', [ClientController::class, 'edit'])->whereNumber('license')->name('clients.edit');
    Route::put('clientes/{license}', [ClientController::class, 'update'])->whereNumber('license')->name('clients.update');

    Route::get('soporte', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('soporte/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
    Route::post('soporte/{ticket}/responder', [TicketController::class, 'reply'])->whereNumber('ticket')->name('tickets.reply');
    Route::post('soporte/{ticket}/estado', [TicketController::class, 'status'])->whereNumber('ticket')->name('tickets.status');
});
