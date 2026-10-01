<?php

namespace App\Listeners;

use App\Events\LoadClosed;
use App\Notifications\LoadReadyNotification;
use App\Support\Recipients;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Al cerrar una carga se avisa a quienes pueden despacharla. Nunca interrumpe el cierre. */
class SendLoadReadyNotification
{
    public function handle(LoadClosed $event): void
    {
        try {
            $load = $event->load;
            $users = Recipients::withPermission('loads.dispatch')
                ->reject(fn ($u) => $load->closed_by !== null && (int) $u->id === (int) $load->closed_by);

            if ($users->isEmpty()) {
                return;
            }

            Notification::send($users, new LoadReadyNotification(
                $load->getKey(),
                (string) ($load->number ?? ('#'.$load->getKey())),
                $load->total_crates !== null ? (int) $load->total_crates : null,
                $load->total_kg !== null ? (float) $load->total_kg : null,
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
