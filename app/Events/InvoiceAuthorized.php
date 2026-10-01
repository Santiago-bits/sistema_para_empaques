<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** ARCA autorizó un comprobante (CAE). */
class InvoiceAuthorized
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Invoice $invoice)
    {
    }
}
