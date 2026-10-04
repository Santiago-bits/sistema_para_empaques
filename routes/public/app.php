<?php

use App\Http\Controllers\AppManifestController;
use App\Http\Controllers\BrandingController;
use Illuminate\Support\Facades\Route;

// App instalable (PWA): ficha con nombre e íconos. Pública: el navegador la pide antes de iniciar sesión.
Route::get('/manifest.webmanifest', AppManifestController::class)->name('app.manifest');

// Logo de la empresa: se sirve con PHP porque Hostinger no permite el enlace public/storage (symlink).
Route::get('/marca/logo', [BrandingController::class, 'logo'])->name('branding.logo');
