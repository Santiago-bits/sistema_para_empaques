<?php

use App\Http\Controllers\EmpaqueController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => to_route('empaques.index'));

Route::resource('empaques', EmpaqueController::class);
