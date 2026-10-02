<?php

namespace App\Listeners;

use App\Events\LoadDispatched;
use App\Services\AccountService;
use Throwable;

/** Carga despachada con flete → cuenta corriente del transportista (al haber: se le debe el viaje). */
class PostLoadFreight
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function handle(LoadDispatched $event): void
    {
        try {
            $this->accounts->postFreight($event->load->fresh() ?? $event->load);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
