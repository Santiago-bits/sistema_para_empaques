<?php

use App\Http\Controllers\HelpController;
use Illuminate\Support\Facades\Route;

/*
| Ayuda: disponible para cualquier usuario con sesión (sin permiso propio).
*/

Route::get('ayuda/atajos', [HelpController::class, 'shortcuts'])->name('help.shortcuts');
