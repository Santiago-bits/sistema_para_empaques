<?php

namespace App\Notifications\Channels;

use App\Jobs\SendWhatsAppMessage;
use App\Services\WhatsApp\WhatsApp;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Canal de notificaciones por WhatsApp (opcional). La notificación debe implementar
 * toWhatsApp($notifiable): ?string. Nunca lanza excepciones hacia el llamador.
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        try {
            if (! WhatsApp::enabled() || ! method_exists($notification, 'toWhatsApp')) {
                return;
            }

            $to = method_exists($notifiable, 'routeNotificationFor')
                ? ($notifiable->routeNotificationFor('whatsapp', $notification) ?? $notifiable->phone ?? null)
                : ($notifiable->phone ?? null);
            $message = $notification->toWhatsApp($notifiable);

            if (! $to || ! $message) {
                return;
            }

            SendWhatsAppMessage::dispatch((string) $to, (string) $message);
        } catch (Throwable $e) {
            Log::warning('[WhatsApp] No se pudo encolar la notificación: '.class_basename($e));
        }
    }
}
