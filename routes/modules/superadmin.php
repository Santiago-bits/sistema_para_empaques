<?php

use App\Http\Controllers\SuperAdmin\ClientController;
use App\Http\Controllers\SuperAdmin\HubController;
use App\Http\Controllers\SuperAdmin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Administración general (/administradorgeneral): sólo el Super Administrador, volviendo a escribir su
| contraseña cada 15 minutos. Para cualquier otro usuario la sección no existe (404).
*/

Route::prefix('administradorgeneral')->name('superadmin.')->middleware('superadmin')->group(function () {
    Route::get('confirmar', [HubController::class, 'confirm'])->name('confirm');
    Route::post('confirmar', [HubController::class, 'confirmStore'])->middleware('throttle:5,1')->name('confirm.store');
    Route::post('salir', [HubController::class, 'lock'])->name('lock');

    Route::get('/', [HubController::class, 'index'])->name('index');
    Route::put('gestion-de-clientes', [HubController::class, 'centralPanel'])->name('central');

    Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
    Route::get('usuarios/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show');
    Route::post('usuarios/{user}/contrasena-temporal', [UserController::class, 'resetPassword'])->whereNumber('user')->middleware('throttle:10,1')->name('users.password');
    Route::put('usuarios/{user}/acceso', [UserController::class, 'updateAccess'])->whereNumber('user')->name('users.access');
    Route::put('usuarios/{user}/estado', [UserController::class, 'updateStatus'])->whereNumber('user')->name('users.status');
    Route::post('usuarios/{user}/cerrar-sesiones', [UserController::class, 'terminateSessions'])->whereNumber('user')->name('users.sessions');

    // Clientes y pagos: sólo con la gestión de clientes (Panel General) activada.
    Route::middleware('central')->group(function () {
        Route::get('clientes', [ClientController::class, 'index'])->name('clients.index');
        Route::get('clientes/{license}', [ClientController::class, 'show'])->whereNumber('license')->name('clients.show');
        Route::put('clientes/{license}/cuota', [ClientController::class, 'updateFee'])->whereNumber('license')->name('clients.fee');
        Route::post('clientes/{license}/pagos', [ClientController::class, 'storePayment'])->whereNumber('license')->middleware('throttle:30,1')->name('clients.payments.store');
        Route::post('clientes/{license}/pagos/{payment}/anular', [ClientController::class, 'voidPayment'])->whereNumber(['license', 'payment'])->name('clients.payments.void');
    });
});
