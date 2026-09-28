<?php

use App\Http\Controllers\EmpaqueController;
use App\Http\Controllers\EmpaqueQrController;
use App\Http\Controllers\LectorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => to_route('empaques.index'));

// withTrashed en show: escanear el QR de un empaque dado de baja muestra el aviso en vez de un 404.
Route::resource('empaques', EmpaqueController::class)->withTrashed(['show']);

// Código QR de cada empaque
Route::controller(EmpaqueQrController::class)
    ->prefix('empaques/{empaque}')
    ->name('empaques.')
    ->group(function () {
        Route::get('qr.{formato}', 'imagen')->whereIn('formato', ['png', 'svg'])->name('qr');
        Route::get('qr/descargar.{formato}', 'descargar')->whereIn('formato', ['png', 'svg'])->name('qr.descargar');
        Route::get('etiqueta', 'etiqueta')->name('etiqueta');
    });

// Lector QR
Route::get('lector', [LectorController::class, 'index'])->name('lector');
Route::get('lector/buscar', [LectorController::class, 'buscar'])->name('lector.buscar');
