<?php

use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

// Términos y condiciones y política de privacidad: públicos (se ven sin ingresar) y con su aceptación.
Route::middleware('throttle:public')->group(function () {
    Route::get('/terminos', [LegalController::class, 'terms'])->name('legal.terms');
    Route::get('/privacidad', [LegalController::class, 'privacy'])->name('legal.privacy');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/terminos/aceptar', [LegalController::class, 'accept'])->name('legal.accept');
    Route::post('/terminos/aceptar', [LegalController::class, 'store'])->middleware('throttle:10,1')->name('legal.accept.store');
});
