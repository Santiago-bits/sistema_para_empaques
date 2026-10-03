<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Asistente de instalación: sólo funciona mientras no existan usuarios.
Route::middleware('throttle:public')->group(function () {
    Route::get('/instalar', [InstallController::class, 'show'])->name('install.show');
    Route::post('/instalar', [InstallController::class, 'store'])->name('install.store');
    // Pocos intentos: pide la contraseña de la base como prueba de que es el dueño del servidor.
    Route::post('/instalar/apartar-tablas', [InstallController::class, 'archive'])->middleware('throttle:5,1')->name('install.archive');
});
