<?php

use App\Http\Controllers\AlertController;
use Illuminate\Support\Facades\Route;

/*
| Alertas del sistema (las genera galpon:check-alerts cada 5 minutos y los servicios en tiempo real).
*/

Route::middleware('can:alerts.view')->group(function () {
    Route::get('alertas', [AlertController::class, 'index'])->name('alerts.index');

    Route::middleware('can:alerts.manage')->group(function () {
        Route::post('alertas/evaluar', [AlertController::class, 'check'])->middleware('throttle:6,1')->name('alerts.check');
        Route::post('alertas/{alert}/resolver', [AlertController::class, 'resolve'])->whereNumber('alert')->name('alerts.resolve');
    });
});
