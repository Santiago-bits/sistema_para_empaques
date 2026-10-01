<?php

namespace App\Events;

use App\Models\Crate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Un cajón fue registrado en producción (escaneo). */
class CrateProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Crate $crate)
    {
    }
}
