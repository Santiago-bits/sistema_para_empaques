<?php

use App\Http\Controllers\EmpaqueController;
use App\Http\Controllers\EmpaqueQrController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => to_route('empaques.index'));

Route::resource('empaques', EmpaqueController::class);

// Código QR de cada empaque
Route::controller(EmpaqueQrController::class)
    ->prefix('empaques/{empaque}')
    ->name('empaques.')
    ->group(function () {
        Route::get('qr.{formato}', 'imagen')->whereIn('formato', ['png', 'svg'])->name('qr');
        Route::get('qr/descargar.{formato}', 'descargar')->whereIn('formato', ['png', 'svg'])->name('qr.descargar');
        Route::get('etiqueta', 'etiqueta')->name('etiqueta');
    });
