<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/*
| Búsqueda global: cada grupo de resultados se filtra por permiso y módulo dentro de SearchService.
*/

Route::get('buscar', SearchController::class)->middleware('throttle:60,1')->name('search');
