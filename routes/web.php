<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
| Las rutas de cada módulo viven en routes/modules/*.php y se cargan dentro
| del grupo autenticado. Cada archivo aplica sus middleware `module:` y `can:`.
| Las rutas públicas (consulta de remito por QR, instalación) van en
| routes/public/*.php.
*/

foreach (glob(__DIR__.'/public/*.php') as $file) {
    require $file;
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/recuperar-contrasena', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordResetController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [PasswordResetController::class, 'edit'])->where('token', '[A-Za-z0-9]+')->name('password.reset');
    Route::post('/restablecer-contrasena', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.store');
});

Route::middleware(['auth', 'active', 'password.fresh', 'kiosk'])->group(function () {
    Route::get('/cambiar-contrasena', [ChangePasswordController::class, 'edit'])->name('password.change');
    Route::put('/cambiar-contrasena', [ChangePasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.change.update');
    Route::get('/', HomeController::class)->name('home');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/perfil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/perfil/tema', [ProfileController::class, 'updateTheme'])->name('profile.theme');

    foreach (glob(__DIR__.'/modules/*.php') as $file) {
        require $file;
    }
});
