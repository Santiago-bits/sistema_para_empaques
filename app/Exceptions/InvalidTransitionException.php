<?php

namespace App\Exceptions;

class InvalidTransitionException extends BusinessException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("No se permite pasar de «{$from}» a «{$to}».", 'invalid_transition', compact('from', 'to'));
    }
}
