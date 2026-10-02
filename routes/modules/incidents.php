<?php

use App\Http\Controllers\IncidentController;
use Illuminate\Support\Facades\Route;

/*
| Incidentes operativos: registro, responsable, seguimiento de estado e historial.
*/

Route::middleware(['module:incidents', 'can:incidents.view'])->group(function () {
    Route::get('incidentes', [IncidentController::class, 'index'])->name('incidents.index');

    Route::middleware('can:incidents.manage')->group(function () {
        Route::get('incidentes/nuevo', [IncidentController::class, 'create'])->name('incidents.create');
        Route::post('incidentes', [IncidentController::class, 'store'])->name('incidents.store');
        Route::get('incidentes/{incident}/editar', [IncidentController::class, 'edit'])->whereNumber('incident')->name('incidents.edit');
        Route::put('incidentes/{incident}', [IncidentController::class, 'update'])->whereNumber('incident')->name('incidents.update');
        Route::post('incidentes/{incident}/estado', [IncidentController::class, 'status'])->whereNumber('incident')->name('incidents.status');
    });

    Route::get('incidentes/{incident}', [IncidentController::class, 'show'])->whereNumber('incident')->name('incidents.show');
});
