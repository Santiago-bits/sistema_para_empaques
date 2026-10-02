<?php

namespace App\View\Composers;

use App\Models\Alert;
use App\Models\License;
use App\Support\Menu;
use App\Support\Shortcuts;
use Illuminate\View\View;

/** Datos comunes del layout: menú, atajos de teclado, contadores de notificaciones/alertas y licencia. */
class AppLayoutComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $menu = Menu::for($user);

        $view->with([
            'menu' => $menu,
            'shortcuts' => Shortcuts::for($menu),
            'unreadCount' => $user->unreadNotifications()->count(),
            'openAlertsCount' => $user->can('alerts.view') ? Alert::query()->open()->count() : 0,
            // Cotización del dólar arriba en todas las pantallas (sólo para quien maneja plata).
            'usdRate' => module_enabled('treasury') && ($user->can('treasury.view') || $user->can('billing.view'))
                ? \App\Models\ExchangeRate::current() : null,
            'license' =>License::query()->where('installation_id', config('galpon.installation_id'))->first(),
        ]);
    }
}
