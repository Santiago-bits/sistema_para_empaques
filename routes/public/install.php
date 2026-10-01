<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Asistente de instalación: sólo funciona mientras no existan usuarios.
Route::middleware('throttle:public')->group(function () {
    Route::get('/instalar', [InstallController::class, 'show'])->name('install.show');
    Route::post('/instalar', [InstallController::class, 'store'])->name('install.store');
});
