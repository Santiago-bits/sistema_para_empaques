<?php

use App\Http\Controllers\Auth\LoginController;
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
});

Route::middleware(['auth', 'active', 'kiosk'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/perfil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/perfil/tema', [ProfileController::class, 'updateTheme'])->name('profile.theme');

    // Latido para detectar pérdida de conexión con el servidor desde las PCs del galpón.
    Route::get('/heartbeat', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]))->name('heartbeat');

    foreach (glob(__DIR__.'/modules/*.php') as $file) {
        require $file;
    }
});
