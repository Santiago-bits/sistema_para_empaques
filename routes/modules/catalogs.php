<?php

use App\Catalogs\CatalogRegistry;
use App\Http\Controllers\Catalogs\CatalogController;
use App\Http\Controllers\Catalogs\LotController;
use App\Http\Controllers\Catalogs\PackerBadgeController;
use Illuminate\Support\Facades\Route;

/*
| Catálogos (Fase 2). Las rutas de cada catálogo se generan desde su
| CatalogDefinition (app/Catalogs/Definitions): nombre `catalogs.<clave>.*`
| (embaladores: `packers.*`). El parámetro `catalog` se fija como default.
*/

Route::middleware('module:catalogs')->group(function () {
    Route::get('catalogos', [CatalogController::class, 'hub'])->name('catalogs.index');

    // Credenciales de embaladores (antes de las rutas genéricas para no chocar con {record}).
    Route::middleware('can:packers.view')->group(function () {
        Route::get('catalogos/embaladores/credenciales', [PackerBadgeController::class, 'many'])->name('packers.badges');
        Route::get('catalogos/embaladores/{packer}/credencial', [PackerBadgeController::class, 'show'])->whereNumber('packer')->name('packers.badge');
    });

    foreach (CatalogRegistry::all() as $definition) {
        $view = 'can:'.$definition->viewPermission();
        $manage = 'can:'.$definition->managePermission();

        Route::prefix($definition->uri())
            ->name($definition->routeName().'.')
            ->controller(CatalogController::class)
            ->group(function () use ($definition, $view, $manage) {
                $key = $definition->key();
                Route::get('/', 'index')->middleware($view)->defaults('catalog', $key)->name('index');
                Route::get('nuevo', 'create')->middleware($manage)->defaults('catalog', $key)->name('create');
                Route::post('/', 'store')->middleware($manage)->defaults('catalog', $key)->name('store');
                Route::get('{record}', 'show')->middleware($view)->whereNumber('record')->defaults('catalog', $key)->name('show');
                Route::get('{record}/editar', 'edit')->middleware($manage)->whereNumber('record')->defaults('catalog', $key)->name('edit');
                Route::put('{record}', 'update')->middleware($manage)->whereNumber('record')->defaults('catalog', $key)->name('update');
                Route::patch('{record}/estado', 'toggle')->middleware($manage)->whereNumber('record')->defaults('catalog', $key)->name('toggle');
                Route::delete('{record}', 'destroy')->middleware($manage)->whereNumber('record')->defaults('catalog', $key)->name('destroy');
            });
    }

    // Lotes
    Route::prefix('lotes')->name('lots.')->controller(LotController::class)->group(function () {
        Route::get('/', 'index')->middleware('can:lots.view')->name('index');
        Route::get('nuevo', 'create')->middleware('can:lots.manage')->name('create');
        Route::post('/', 'store')->middleware('can:lots.manage')->name('store');
        Route::get('{lot}', 'show')->middleware('can:lots.view')->name('show');
        Route::get('{lot}/editar', 'edit')->middleware('can:lots.manage')->name('edit');
        Route::put('{lot}', 'update')->middleware('can:lots.manage')->name('update');
        Route::post('{lot}/cerrar', 'close')->middleware('can:lots.manage')->name('close');
        Route::post('{lot}/anular', 'void')->middleware('can:lots.manage')->name('void');
    });
});
