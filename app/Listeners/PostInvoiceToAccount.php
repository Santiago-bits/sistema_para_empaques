<?php

namespace App\Listeners;

use App\Events\InvoiceAuthorized;
use App\Services\AccountService;
use Throwable;

/** Comprobante autorizado → cuenta corriente del cliente (factura al debe, nota de crédito al haber). */
class PostInvoiceToAccount
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function handle(InvoiceAuthorized $event): void
    {
        try {
            $this->accounts->postInvoice($event->invoice->fresh() ?? $event->invoice);
        } catch (Throwable $e) {
            // Si falla, «Imputar pendientes» en Cuentas corrientes lo vuelve a intentar.
            report($e);
        }
    }
}
