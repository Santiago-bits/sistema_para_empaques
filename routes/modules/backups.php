<?php

use App\Http\Controllers\Admin\BackupController;
use Illuminate\Support\Facades\Route;

/*
| Backups: generar, descargar, verificar y restaurar (restaurar requiere backups.restore + doble confirmación).
*/

Route::middleware('can:backups.manage')->group(function () {
    Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('backups', [BackupController::class, 'store'])->middleware('throttle:5,10')->name('backups.store');
    Route::get('backups/{backup}/descargar', [BackupController::class, 'download'])->whereNumber('backup')->name('backups.download');
    Route::post('backups/{backup}/verificar', [BackupController::class, 'verify'])->whereNumber('backup')->middleware('throttle:20,1')->name('backups.verify');
    Route::post('backups/{backup}/restaurar', [BackupController::class, 'restore'])->whereNumber('backup')
        ->middleware(['can:backups.restore', 'throttle:3,10'])->name('backups.restore');
});
