<?php

namespace App\Events;

use App\Models\Remito;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Se emitió un remito. */
class RemitoIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Remito $remito)
    {
    }
}
