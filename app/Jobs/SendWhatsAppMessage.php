<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Envío de WhatsApp en segundo plano: un proveedor lento o caído no demora la operación. */
class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly string $to, public readonly string $message)
    {
    }

    public function handle(): void
    {
        if (! WhatsApp::enabled()) {
            return;
        }

        try {
            WhatsApp::client()->send($this->to, $this->message);
        } catch (Throwable $e) {
            Log::warning('[WhatsApp] Error al enviar mensaje a '.WhatsApp::maskPhone($this->to).': '.class_basename($e));
        }
    }
}
