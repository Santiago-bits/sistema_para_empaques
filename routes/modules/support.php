<?php

use App\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

/*
| Soporte técnico: tickets entre el galpón y el desarrollador del sistema.
*/

Route::middleware('can:support.use')->group(function () {
    Route::get('soporte', [SupportController::class, 'index'])->name('support.index');
    Route::get('soporte/nuevo', [SupportController::class, 'create'])->name('support.create');
    Route::post('soporte', [SupportController::class, 'store'])->middleware('throttle:10,10')->name('support.store');
    Route::get('soporte/{ticket}', [SupportController::class, 'show'])->whereNumber('ticket')->name('support.show');
    Route::post('soporte/{ticket}/responder', [SupportController::class, 'reply'])->whereNumber('ticket')->middleware('throttle:30,10')->name('support.reply');
    Route::post('soporte/{ticket}/estado', [SupportController::class, 'status'])->whereNumber('ticket')->name('support.status');
});
