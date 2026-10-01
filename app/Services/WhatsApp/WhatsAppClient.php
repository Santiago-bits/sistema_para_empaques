<?php

namespace App\Services\WhatsApp;

/**
 * Cliente de WhatsApp (integración OPCIONAL). El sistema nunca depende de WhatsApp:
 * cualquier error se captura y se registra en el log sin interrumpir la operación.
 */
interface WhatsAppClient
{
    /** Envía un mensaje de texto. Devuelve true si el proveedor lo aceptó. */
    public function send(string $to, string $message): bool;
}
