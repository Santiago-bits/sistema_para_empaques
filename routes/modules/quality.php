<?php

use App\Http\Controllers\Quality\QualityControlController;
use App\Http\Controllers\Quality\RejectController;
use Illuminate\Support\Facades\Route;

/*
| Calidad: controles de calidad, rechazos y merma.
*/

Route::middleware(['module:quality', 'can:quality.view'])->group(function () {
    Route::get('calidad', [QualityControlController::class, 'index'])->name('quality.index');
    Route::middleware('can:quality.manage')->group(function () {
        Route::get('calidad/nuevo', [QualityControlController::class, 'create'])->name('quality.create');
        Route::get('calidad/buscar', [QualityControlController::class, 'lookup'])->name('quality.lookup');
        Route::post('calidad', [QualityControlController::class, 'store'])->name('quality.store');
    });
    Route::get('calidad/{qualityControl}', [QualityControlController::class, 'show'])->whereNumber('qualityControl')->name('quality.show');

    Route::get('rechazos', [RejectController::class, 'index'])->name('rejects.index');
    Route::middleware('can:quality.manage')->group(function () {
        Route::get('rechazos/nuevo', [RejectController::class, 'create'])->name('rejects.create');
        Route::post('rechazos', [RejectController::class, 'store'])->name('rejects.store');
    });
});
