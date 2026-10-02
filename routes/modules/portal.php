<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

/*
| Portal de cliente / propietario (sólo lectura, filtrado por owner_id / client_id del usuario).
*/

Route::middleware(['module:client_portal', 'can:portal.view'])->group(function () {
    Route::get('portal', [PortalController::class, 'index'])->name('portal.index');
    Route::get('portal/facturas/{invoice}/pdf', [PortalController::class, 'invoicePdf'])->whereNumber('invoice')->name('portal.invoices.pdf');
});
