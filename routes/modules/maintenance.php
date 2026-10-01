<?php

use App\Http\Controllers\Maintenance\MachineController;
use Illuminate\Support\Facades\Route;

/*
| Mantenimiento: máquinas y mantenimientos (preventivo / correctivo / emergencia).
*/

Route::middleware(['module:maintenance', 'can:maintenance.view'])->group(function () {
    Route::get('maquinas', [MachineController::class, 'index'])->name('machines.index');

    Route::middleware('can:maintenance.manage')->group(function () {
        Route::get('maquinas/nueva', [MachineController::class, 'create'])->name('machines.create');
        Route::post('maquinas', [MachineController::class, 'store'])->name('machines.store');
        Route::get('maquinas/{machine}/editar', [MachineController::class, 'edit'])->whereNumber('machine')->name('machines.edit');
        Route::put('maquinas/{machine}', [MachineController::class, 'update'])->whereNumber('machine')->name('machines.update');
        Route::post('maquinas/{machine}/mantenimientos', [MachineController::class, 'storeMaintenance'])->whereNumber('machine')->name('machines.maintenances.store');
    });

    Route::get('maquinas/{machine}', [MachineController::class, 'show'])->whereNumber('machine')->name('machines.show');
});
