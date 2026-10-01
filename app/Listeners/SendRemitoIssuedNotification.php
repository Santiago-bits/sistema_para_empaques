<?php

namespace App\Listeners;

use App\Events\RemitoIssued;
use App\Notifications\RemitoIssuedNotification;
use App\Support\Recipients;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Al emitir un remito se avisa a facturación. */
class SendRemitoIssuedNotification
{
    public function handle(RemitoIssued $event): void
    {
        try {
            $remito = $event->remito;
            $users = Recipients::withPermission('billing.manage')
                ->reject(fn ($u) => $remito->created_by !== null && (int) $u->id === (int) $remito->created_by);

            if ($users->isEmpty()) {
                return;
            }

            Notification::send($users, new RemitoIssuedNotification(
                $remito->getKey(),
                (string) ($remito->number ?? ('#'.$remito->getKey())),
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
