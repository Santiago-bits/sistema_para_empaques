<?php

use App\Http\Controllers\Developer\DeveloperController;
use App\Http\Controllers\Developer\LicenseController;
use Illuminate\Support\Facades\Route;

/*
| Panel del desarrollador: sólo super administrador (gate «developer»).
*/

Route::prefix('desarrollador')->name('developer.')->middleware('can:developer')->group(function () {
    Route::get('/', [DeveloperController::class, 'index'])->name('index');
    Route::get('errores', [DeveloperController::class, 'errors'])->name('errors');
    Route::get('errores/{error}', [DeveloperController::class, 'error'])->whereNumber('error')->name('errors.show');
    Route::get('logs', [DeveloperController::class, 'logs'])->middleware('can:logs.view')->name('logs');

    Route::get('licencias', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('licencias/nueva', [LicenseController::class, 'create'])->name('licenses.create');
    Route::post('licencias', [LicenseController::class, 'store'])->name('licenses.store');
    Route::get('licencias/{license}/editar', [LicenseController::class, 'edit'])->whereNumber('license')->name('licenses.edit');
    Route::put('licencias/{license}', [LicenseController::class, 'update'])->whereNumber('license')->name('licenses.update');
});
