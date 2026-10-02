<?php

use App\Http\Controllers\Reports\ClosingController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\StatsController;
use Illuminate\Support\Facades\Route;

/*
| Reportes (exportables con los filtros activos), estadísticas y cierre diario.
*/

Route::middleware(['module:reports'])->group(function () {
    Route::middleware('can:reports.view')->group(function () {
        Route::get('reportes', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reportes/descargas/{file}', [ReportController::class, 'download'])
            ->where('file', '[A-Za-z0-9_\-.]+')->name('reports.downloads.show');
        Route::get('reportes/{report}', [ReportController::class, 'show'])
            ->where('report', '[a-z_\-]+')->name('reports.show');
    });

    Route::get('estadisticas', StatsController::class)->middleware('can:stats.view')->name('stats.index');
});

Route::middleware('can:closings.manage')->group(function () {
    Route::get('cierres', [ClosingController::class, 'index'])->name('closings.index');
    Route::get('cierres/nuevo', [ClosingController::class, 'create'])->name('closings.create');
    Route::post('cierres', [ClosingController::class, 'store'])->middleware('throttle:10,1')->name('closings.store');
    Route::get('cierres/{closing}', [ClosingController::class, 'show'])->whereNumber('closing')->name('closings.show');
    Route::post('cierres/{closing}/reabrir', [ClosingController::class, 'reopen'])
        ->whereNumber('closing')->middleware('can:closings.reopen')->name('closings.reopen');
});
