<?php

use App\Http\Controllers\ColdRooms\ColdRoomController;
use Illuminate\Support\Facades\Route;

/*
| Cámaras frigoríficas: rangos, lecturas de temperatura/humedad y alertas.
*/

Route::middleware(['module:cold_rooms', 'can:cold_rooms.view'])->group(function () {
    Route::get('camaras', [ColdRoomController::class, 'index'])->name('cold-rooms.index');

    Route::middleware('can:cold_rooms.manage')->group(function () {
        Route::get('camaras/nueva', [ColdRoomController::class, 'create'])->name('cold-rooms.create');
        Route::post('camaras', [ColdRoomController::class, 'store'])->name('cold-rooms.store');
        Route::get('camaras/{coldRoom}/editar', [ColdRoomController::class, 'edit'])->whereNumber('coldRoom')->name('cold-rooms.edit');
        Route::put('camaras/{coldRoom}', [ColdRoomController::class, 'update'])->whereNumber('coldRoom')->name('cold-rooms.update');
        Route::post('camaras/{coldRoom}/lecturas', [ColdRoomController::class, 'storeReading'])->whereNumber('coldRoom')->name('cold-rooms.readings.store');
    });

    Route::get('camaras/{coldRoom}', [ColdRoomController::class, 'show'])->whereNumber('coldRoom')->name('cold-rooms.show');
});
