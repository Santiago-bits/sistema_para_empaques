<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\WhatsAppChannel;
use App\Services\WhatsApp\WhatsApp;

/**
 * Canales estándar de las notificaciones internas: siempre base de datos (centro de
 * notificaciones) y, si la integración está activa y el usuario tiene teléfono, WhatsApp.
 */
trait SendsToDatabaseAndWhatsApp
{
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($this, 'toWhatsApp') && ! empty($notifiable->phone) && WhatsApp::enabled()) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    /** Estructura común guardada en notifications.data. */
    protected function payload(string $kind, string $title, ?string $message, ?string $url, string $level = 'info', array $extra = []): array
    {
        return array_merge([
            'kind' => $kind,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'level' => $level,
        ], $extra);
    }

    protected static function safeRoute(string $name, mixed $parameters = []): ?string
    {
        try {
            return \Illuminate\Support\Facades\Route::has($name) ? route($name, $parameters) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
