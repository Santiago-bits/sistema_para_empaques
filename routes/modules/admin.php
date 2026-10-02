<?php

use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Núcleo: usuarios, roles/permisos, módulos, configuración, auditoría, sesiones.
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('can:users.view')->group(function () {
        Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
        Route::get('usuarios/nuevo', [UserController::class, 'create'])->middleware('can:users.manage')->name('users.create');
        Route::post('usuarios', [UserController::class, 'store'])->middleware('can:users.manage')->name('users.store');
        Route::get('usuarios/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->middleware('can:users.manage')->name('users.edit');
        Route::put('usuarios/{user}', [UserController::class, 'update'])->middleware('can:users.manage')->name('users.update');
        Route::post('usuarios/{user}/restablecer-contrasena', [UserController::class, 'resetPassword'])->middleware(['can:users.manage', 'throttle:10,1'])->name('users.password.reset');
        Route::get('usuarios/{user}/permisos', [UserController::class, 'permissions'])->middleware('can:roles.manage')->name('users.permissions');
        Route::put('usuarios/{user}/permisos', [UserController::class, 'updatePermissions'])->middleware('can:roles.manage')->name('users.permissions.update');
    });

    Route::middleware('can:api.tokens')->group(function () {
        Route::get('tokens', [ApiTokenController::class, 'index'])->name('tokens.index');
        Route::post('tokens', [ApiTokenController::class, 'store'])->middleware('throttle:10,1')->name('tokens.store');
        Route::delete('tokens/{token}', [ApiTokenController::class, 'destroy'])->whereNumber('token')->name('tokens.destroy');
    });

    Route::middleware('can:roles.manage')->group(function () {
        Route::resource('roles', RoleController::class)->except('show')->parameters(['roles' => 'role']);
    });

    Route::middleware('can:modules.manage')->group(function () {
        Route::get('modulos', [ModuleController::class, 'index'])->name('modules.index');
        Route::put('modulos/{module}', [ModuleController::class, 'update'])->name('modules.update');
    });

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('configuracion', [SettingController::class, 'index'])->name('settings.index');
        Route::post('configuracion/{group}', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('can:audit.view')->group(function () {
        Route::get('auditoria', [AuditController::class, 'index'])->name('audit.index');
        Route::get('auditoria/{log}', [AuditController::class, 'show'])->name('audit.show');
    });

    Route::middleware('can:sessions.manage')->group(function () {
        Route::get('sesiones', [SessionController::class, 'index'])->name('sessions.index');
        Route::delete('sesiones/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
    });
});
