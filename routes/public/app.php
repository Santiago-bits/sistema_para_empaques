<?php

use App\Http\Controllers\AppManifestController;
use Illuminate\Support\Facades\Route;

// App instalable (PWA): ficha con nombre e íconos. Pública: el navegador la pide antes de iniciar sesión.
Route::get('/manifest.webmanifest', AppManifestController::class)->name('app.manifest');
