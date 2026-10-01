<?php

namespace App\Services\WhatsApp;

/**
 * Punto de acceso a la integración de WhatsApp. Sólo se usa si el módulo `whatsapp`
 * está activo y la configuración `whatsapp.enabled` está encendida.
 */
class WhatsApp
{
    public static function enabled(): bool
    {
        try {
            return module_enabled('whatsapp') && (bool) setting('whatsapp.enabled', false);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Cliente configurado (en tests se puede reemplazar con app()->instance(WhatsAppClient::class, ...)). */
    public static function client(): WhatsAppClient
    {
        if (app()->bound(WhatsAppClient::class)) {
            return app(WhatsAppClient::class);
        }

        return match (config('galpon.whatsapp.driver', 'log')) {
            'cloud_api' => new CloudApiWhatsAppClient(
                config('galpon.whatsapp.token'),
                config('galpon.whatsapp.phone_number_id'),
                (string) config('galpon.whatsapp.api_version', 'v21.0'),
            ),
            default => new LogWhatsAppClient,
        };
    }

    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return strlen($digits) > 4 ? str_repeat('*', strlen($digits) - 4).substr($digits, -4) : '****';
    }
}
