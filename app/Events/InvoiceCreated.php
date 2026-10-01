<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Se creó un comprobante. */
class InvoiceCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Invoice $invoice)
    {
    }
}
