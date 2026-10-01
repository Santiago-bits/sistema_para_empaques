<?php

namespace App\Exceptions;

/** Otro usuario modificó el registro al mismo tiempo; la operación no se aplicó. */
class ConcurrencyException extends BusinessException
{
    public function __construct(string $message = 'Otro usuario modificó este registro al mismo tiempo. Actualizá la pantalla e intentá nuevamente.')
    {
        parent::__construct($message, 'concurrency');
    }
}
