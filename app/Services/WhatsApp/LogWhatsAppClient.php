<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Driver por defecto: no envía nada, sólo deja constancia en el log. */
class LogWhatsAppClient implements WhatsAppClient
{
    public function send(string $to, string $message): bool
    {
        Log::info('[WhatsApp:log] Mensaje a '.WhatsApp::maskPhone($to).': '.Str::limit($message, 300));

        return true;
    }
}
