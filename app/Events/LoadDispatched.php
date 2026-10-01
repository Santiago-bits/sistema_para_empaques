<?php

namespace App\Events;

use App\Models\Load;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Una carga fue despachada (checklist completo). */
class LoadDispatched
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Load $load)
    {
    }
}
