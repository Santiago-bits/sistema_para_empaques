<?php

namespace App\View\Composers;

use App\Models\Alert;
use App\Models\License;
use App\Support\Menu;
use Illuminate\View\View;

/** Datos comunes del layout: menú, contadores de notificaciones/alertas y licencia. */
class AppLayoutComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $view->with([
            'menu' => Menu::for($user),
            'unreadCount' => $user->unreadNotifications()->count(),
            'openAlertsCount' => $user->can('alerts.view') ? Alert::query()->open()->count() : 0,
            'license' => License::query()->where('installation_id', config('galpon.installation_id'))->first(),
        ]);
    }
}
