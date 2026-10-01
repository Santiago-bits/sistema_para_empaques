<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp Cloud API (Meta Graph API). El token se lee de .env (WHATSAPP_TOKEN)
 * y NUNCA se escribe en logs ni en mensajes de error.
 */
class CloudApiWhatsAppClient implements WhatsAppClient
{
    public function __construct(
        private readonly ?string $token,
        private readonly ?string $phoneNumberId,
        private readonly string $apiVersion = 'v21.0',
    ) {
    }

    public function send(string $to, string $message): bool
    {
        if (! $this->token || ! $this->phoneNumberId) {
            Log::warning('[WhatsApp] Cloud API sin configurar (falta WHATSAPP_TOKEN o WHATSAPP_PHONE_NUMBER_ID).');

            return false;
        }

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(10)
                ->post('https://graph.facebook.com/'.$this->apiVersion.'/'.rawurlencode($this->phoneNumberId).'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => preg_replace('/\D+/', '', $to),
                    'type' => 'text',
                    'text' => ['preview_url' => false, 'body' => mb_substr($message, 0, 4000)],
                ]);
        } catch (Throwable $e) {
            Log::warning('[WhatsApp] No se pudo conectar con la Cloud API: '.class_basename($e));

            return false;
        }

        if ($response->failed()) {
            // Sólo estado y código de error del proveedor: nunca el token ni los encabezados.
            Log::warning('[WhatsApp] La Cloud API rechazó el mensaje a '.WhatsApp::maskPhone($to)
                .' (HTTP '.$response->status().', código '.($response->json('error.code') ?? 'desconocido').').');

            return false;
        }

        return true;
    }
}
