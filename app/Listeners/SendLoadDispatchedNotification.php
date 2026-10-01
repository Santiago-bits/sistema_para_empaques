<?php

namespace App\Listeners;

use App\Events\LoadDispatched;
use App\Notifications\LoadDispatchedNotification;
use App\Support\Recipients;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Al despachar una carga se avisa a quienes ven cargas (logística, facturación). */
class SendLoadDispatchedNotification
{
    public function handle(LoadDispatched $event): void
    {
        try {
            $load = $event->load;
            $users = Recipients::withPermission('loads.view')
                ->reject(fn ($u) => $load->dispatched_by !== null && (int) $u->id === (int) $load->dispatched_by);

            if ($users->isEmpty()) {
                return;
            }

            $destination = null;
            try {
                $destination = $load->destination_id ? $load->destination?->name : null;
            } catch (Throwable) {
                // Datos mínimos: el destino es opcional.
            }

            Notification::send($users, new LoadDispatchedNotification(
                $load->getKey(),
                (string) ($load->number ?? ('#'.$load->getKey())),
                $destination,
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
