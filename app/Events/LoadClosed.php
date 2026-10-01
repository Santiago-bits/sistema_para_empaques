<?php

namespace App\Events;

use App\Models\Load;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Una carga fue cerrada (ya no admite cambios libres). */
class LoadClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Load $load)
    {
    }
}
