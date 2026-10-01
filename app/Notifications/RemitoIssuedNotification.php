<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsToDatabaseAndWhatsApp;
use Illuminate\Notifications\Notification;

/** Se emitió un remito (aviso para facturación). */
class RemitoIssuedNotification extends Notification
{
    use SendsToDatabaseAndWhatsApp;

    public function __construct(public readonly ?int $remitoId, public readonly string $number)
    {
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            'remito_issued',
            'Remito '.$this->number.' emitido',
            'El remito está listo para facturar.',
            $this->remitoId ? self::safeRoute('remitos.show', $this->remitoId) : self::safeRoute('remitos.index'),
            'info',
            ['remito_id' => $this->remitoId, 'number' => $this->number],
        );
    }
}
