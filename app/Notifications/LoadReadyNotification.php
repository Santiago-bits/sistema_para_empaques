<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsToDatabaseAndWhatsApp;
use Illuminate\Notifications\Notification;

/** Una carga quedó cerrada y lista para despachar. */
class LoadReadyNotification extends Notification
{
    use SendsToDatabaseAndWhatsApp;

    public function __construct(
        public readonly ?int $loadId,
        public readonly string $number,
        public readonly ?int $crates = null,
        public readonly ?float $kg = null,
    ) {
    }

    public function toArray(object $notifiable): array
    {
        $detail = $this->crates !== null
            ? num($this->crates).' cajones'.($this->kg !== null ? ' · '.kg($this->kg) : '')
            : null;

        return $this->payload(
            'load_ready',
            'Carga '.$this->number.' lista para despachar',
            $detail,
            $this->loadId ? self::safeRoute('loads.show', $this->loadId) : self::safeRoute('loads.index'),
            'info',
            ['load_id' => $this->loadId, 'number' => $this->number],
        );
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return 'Carga '.$this->number.' cerrada y lista para despachar.';
    }
}
