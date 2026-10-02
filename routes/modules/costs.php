<?php

use App\Http\Controllers\CostController;
use Illuminate\Support\Facades\Route;

/*
| Costos operativos y rentabilidad (la rentabilidad requiere además profit.view).
*/

Route::middleware(['module:costs', 'can:costs.view'])->group(function () {
    Route::get('costos', [CostController::class, 'index'])->name('costs.index');

    Route::middleware('can:costs.manage')->group(function () {
        Route::get('costos/nuevo', [CostController::class, 'create'])->name('costs.create');
        Route::post('costos', [CostController::class, 'store'])->name('costs.store');
        Route::get('costos/{cost}/editar', [CostController::class, 'edit'])->whereNumber('cost')->name('costs.edit');
        Route::put('costos/{cost}', [CostController::class, 'update'])->whereNumber('cost')->name('costs.update');
        Route::delete('costos/{cost}', [CostController::class, 'destroy'])->whereNumber('cost')->name('costs.destroy');
    });
});
