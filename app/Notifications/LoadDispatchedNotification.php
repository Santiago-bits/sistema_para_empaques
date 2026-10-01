<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsToDatabaseAndWhatsApp;
use Illuminate\Notifications\Notification;

/** Una carga fue despachada. */
class LoadDispatchedNotification extends Notification
{
    use SendsToDatabaseAndWhatsApp;

    public function __construct(
        public readonly ?int $loadId,
        public readonly string $number,
        public readonly ?string $destination = null,
    ) {
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            'load_dispatched',
            'Carga '.$this->number.' despachada',
            $this->destination ? 'Destino: '.$this->destination : null,
            $this->loadId ? self::safeRoute('loads.show', $this->loadId) : self::safeRoute('loads.index'),
            'success',
            ['load_id' => $this->loadId, 'number' => $this->number],
        );
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return 'Carga '.$this->number.' despachada'.($this->destination ? ' con destino '.$this->destination : '').'.';
    }
}
