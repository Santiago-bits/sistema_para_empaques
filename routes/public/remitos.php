<?php

use App\Http\Controllers\Loads\RemitoController;
use Illuminate\Support\Facades\Route;

// Consulta pública del remito (QR impreso): sin login, datos limitados, con límite de solicitudes.
Route::get('/r/{token}', [RemitoController::class, 'public'])->middleware('throttle:public')->name('remitos.public');
