<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Notifications\Concerns\SendsToDatabaseAndWhatsApp;
use Illuminate\Notifications\Notification;

/** Se generó (o reabrió) una alerta de severidad advertencia/crítica. */
class AlertRaised extends Notification
{
    use SendsToDatabaseAndWhatsApp;

    public function __construct(public readonly Alert $alert)
    {
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            'alert',
            $this->alert->title,
            $this->alert->message,
            self::safeRoute('alerts.index', ['type' => $this->alert->type]),
            $this->alert->severity === 'critical' ? 'danger' : 'warning',
            ['alert_id' => $this->alert->id, 'type' => $this->alert->type, 'severity' => $this->alert->severity],
        );
    }

    /** Por WhatsApp sólo se avisan las alertas críticas. */
    public function toWhatsApp(object $notifiable): ?string
    {
        if ($this->alert->severity !== 'critical') {
            return null;
        }

        return '⚠ '.setting('company.name', 'Galpón').': '.$this->alert->title
            .($this->alert->message ? "\n".$this->alert->message : '');
    }
}
