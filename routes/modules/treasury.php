<?php

use App\Http\Controllers\Treasury\AccountController;
use App\Http\Controllers\Treasury\CashController;
use App\Http\Controllers\Treasury\CheckController;
use App\Http\Controllers\Treasury\ExchangeRateController;
use Illuminate\Support\Facades\Route;

/*
| Tesorería: caja, cuentas corrientes, cheques y cotización del dólar.
| Ver = treasury.view; operar cada parte tiene su permiso (cash.manage, accounts.manage, checks.manage…).
*/

Route::middleware(['module:treasury', 'can:treasury.view'])->group(function () {
    // Caja
    Route::get('caja', [CashController::class, 'index'])->name('cash.index');
    Route::get('caja/historial', [CashController::class, 'sessions'])->name('cash.sessions');
    Route::get('caja/{session}', [CashController::class, 'show'])->whereNumber('session')->name('cash.show');
    Route::middleware('can:cash.manage')->group(function () {
        Route::post('caja/abrir', [CashController::class, 'open'])->name('cash.open');
        Route::post('caja/movimientos', [CashController::class, 'storeMovement'])->middleware('throttle:60,1')->name('cash.movements.store');
        Route::post('caja/cerrar', [CashController::class, 'close'])->name('cash.close');
        Route::put('caja/movimientos/{movement}', [CashController::class, 'correctMovement'])->whereNumber('movement')->name('cash.movements.correct');
    });
    Route::post('caja/movimientos/{movement}/anular', [CashController::class, 'voidMovement'])->whereNumber('movement')
        ->middleware('can:accounts.void')->name('cash.movements.void');

    // Cuentas corrientes
    Route::get('cuentas-corrientes', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('cuentas-corrientes/imputar', [AccountController::class, 'sync'])->middleware('can:accounts.manage')->name('accounts.sync');
    Route::put('cuentas-corrientes/movimientos/{movement}', [AccountController::class, 'correct'])->whereNumber('movement')
        ->middleware('can:accounts.manage')->name('accounts.movements.correct');
    Route::post('cuentas-corrientes/movimientos/{movement}/anular', [AccountController::class, 'void'])->whereNumber('movement')
        ->middleware('can:accounts.void')->name('accounts.movements.void');
    Route::prefix('cuentas-corrientes/{type}/{holder}')->whereIn('type', ['client', 'producer', 'transporter', 'provider', 'employee'])
        ->whereNumber('holder')->group(function () {
            Route::get('/', [AccountController::class, 'show'])->name('accounts.show');
            Route::get('imprimir', [AccountController::class, 'print'])->name('accounts.print');
            Route::post('pagos', [AccountController::class, 'payment'])->middleware(['can:accounts.manage', 'throttle:60,1'])->name('accounts.payments.store');
            Route::post('ajustes', [AccountController::class, 'adjust'])->middleware('can:accounts.manage')->name('accounts.adjustments.store');
        });
    Route::post('lotes/{lot}/liquidar', [AccountController::class, 'settleLot'])->whereNumber('lot')->middleware('can:lots.settle')->name('lots.settle');

    // Cheques
    Route::get('cheques', [CheckController::class, 'index'])->name('checks.index');
    Route::get('cheques/{check}', [CheckController::class, 'show'])->whereNumber('check')->name('checks.show');
    Route::middleware('can:checks.manage')->group(function () {
        Route::get('cheques/nuevo', [CheckController::class, 'create'])->name('checks.create');
        Route::post('cheques', [CheckController::class, 'store'])->name('checks.store');
        Route::post('cheques/{check}/estado', [CheckController::class, 'transition'])->whereNumber('check')->name('checks.transition');
        Route::get('cheques/{check}/editar', [CheckController::class, 'edit'])->whereNumber('check')->name('checks.edit');
        Route::put('cheques/{check}', [CheckController::class, 'update'])->whereNumber('check')->name('checks.update');
    });

    // Cotización del dólar
    Route::get('cotizaciones', [ExchangeRateController::class, 'index'])->name('exchange.index');
    Route::post('cotizaciones', [ExchangeRateController::class, 'store'])->middleware('can:exchange.manage')->name('exchange.store');
});
