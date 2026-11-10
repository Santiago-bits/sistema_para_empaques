<?php

use App\Http\Controllers\SuperAdmin\AssociationController;
use App\Http\Controllers\SuperAdmin\ClientController;
use App\Http\Controllers\SuperAdmin\HubController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\SuperAdmin\WarehouseController;
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
    Route::post('datos-de-ejemplo', [HubController::class, 'sampleData'])->middleware('throttle:3,10')->name('sample');
    Route::post('actualizar-base', [HubController::class, 'upgradeDatabase'])->middleware('throttle:5,1')->name('upgrade');

    // Galpones del sistema (multigalpón): cada uno con su administrador general y sus propios datos.
    Route::get('galpones', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::get('galpones/nuevo', [WarehouseController::class, 'create'])->name('warehouses.create');
    Route::post('galpones', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::get('galpones/{warehouse}', [WarehouseController::class, 'show'])->whereNumber('warehouse')->name('warehouses.show');
    Route::get('galpones/{warehouse}/editar', [WarehouseController::class, 'edit'])->whereNumber('warehouse')->name('warehouses.edit');
    Route::put('galpones/{warehouse}', [WarehouseController::class, 'update'])->whereNumber('warehouse')->name('warehouses.update');
    Route::post('galpones/{warehouse}/administrador', [WarehouseController::class, 'storeAdmin'])->whereNumber('warehouse')->middleware('throttle:20,1')->name('warehouses.admin');
    // Asociaciones de citricultores (lista común; cada galpón se vincula desde su ficha).
    Route::get('asociaciones', [AssociationController::class, 'index'])->name('associations.index');
    Route::get('asociaciones/nueva', [AssociationController::class, 'create'])->name('associations.create');
    Route::post('asociaciones', [AssociationController::class, 'store'])->name('associations.store');
    Route::get('asociaciones/{association}/editar', [AssociationController::class, 'edit'])->whereNumber('association')->name('associations.edit');
    Route::put('asociaciones/{association}', [AssociationController::class, 'update'])->whereNumber('association')->name('associations.update');
    Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
    Route::get('usuarios/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show');
    Route::post('usuarios/{user}/contrasena-temporal', [UserController::class, 'resetPassword'])->whereNumber('user')->middleware('throttle:10,1')->name('users.password');
    Route::put('usuarios/{user}/acceso', [UserController::class, 'updateAccess'])->whereNumber('user')->name('users.access');
    Route::put('usuarios/{user}/estado', [UserController::class, 'updateStatus'])->whereNumber('user')->name('users.status');
    Route::post('usuarios/{user}/cerrar-sesiones', [UserController::class, 'terminateSessions'])->whereNumber('user')->name('users.sessions');

    // Instalaciones aparte (galpones con su propio sistema) y pagos: sólo con la gestión de clientes (Panel General) activada.
    Route::middleware('central')->group(function () {
        Route::get('instalaciones', [ClientController::class, 'index'])->name('clients.index');
        Route::get('instalaciones/{license}', [ClientController::class, 'show'])->whereNumber('license')->name('clients.show');
        Route::put('instalaciones/{license}/cuota', [ClientController::class, 'updateFee'])->whereNumber('license')->name('clients.fee');
        Route::post('instalaciones/{license}/pagos', [ClientController::class, 'storePayment'])->whereNumber('license')->middleware('throttle:30,1')->name('clients.payments.store');
        Route::post('instalaciones/{license}/pagos/{payment}/anular', [ClientController::class, 'voidPayment'])->whereNumber(['license', 'payment'])->name('clients.payments.void');
    });
});
