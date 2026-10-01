<?php

use App\Http\Controllers\Supplies\SupplyController;
use Illuminate\Support\Facades\Route;

/*
| Insumos: stock, movimientos (ingreso / egreso / ajuste) y alertas de stock bajo.
*/

Route::middleware(['module:supplies', 'can:supplies.view'])->group(function () {
    Route::get('insumos', [SupplyController::class, 'index'])->name('supplies.index');

    Route::middleware('can:supplies.manage')->group(function () {
        Route::get('insumos/nuevo', [SupplyController::class, 'create'])->name('supplies.create');
        Route::post('insumos', [SupplyController::class, 'store'])->name('supplies.store');
        Route::get('insumos/{supply}/editar', [SupplyController::class, 'edit'])->whereNumber('supply')->name('supplies.edit');
        Route::put('insumos/{supply}', [SupplyController::class, 'update'])->whereNumber('supply')->name('supplies.update');
        Route::post('insumos/{supply}/movimientos', [SupplyController::class, 'storeMovement'])->whereNumber('supply')->name('supplies.movements.store');
    });

    Route::get('insumos/{supply}', [SupplyController::class, 'show'])->whereNumber('supply')->name('supplies.show');
});
