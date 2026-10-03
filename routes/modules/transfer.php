<?php

use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

// Importar y exportar (Excel): cada usuario ve sólo lo que tiene permiso (se filtra en el controlador).
Route::get('importar-exportar', [TransferController::class, 'index'])->name('transfer.index');
