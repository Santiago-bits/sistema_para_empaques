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
        Route::get('calidad/{qualityControl}/editar', [QualityControlController::class, 'edit'])->whereNumber('qualityControl')->name('quality.edit');
        Route::put('calidad/{qualityControl}', [QualityControlController::class, 'update'])->whereNumber('qualityControl')->name('quality.update');
        Route::get('rechazos/{reject}/editar', [RejectController::class, 'edit'])->whereNumber('reject')->name('rejects.edit');
        Route::put('rechazos/{reject}', [RejectController::class, 'update'])->whereNumber('reject')->name('rejects.update');
    });
    Route::get('calidad/{qualityControl}', [QualityControlController::class, 'show'])->whereNumber('qualityControl')->name('quality.show');

    Route::get('rechazos', [RejectController::class, 'index'])->name('rejects.index');
    Route::middleware('can:quality.manage')->group(function () {
        Route::get('rechazos/nuevo', [RejectController::class, 'create'])->name('rejects.create');
        Route::post('rechazos', [RejectController::class, 'store'])->name('rejects.store');
    });
});
