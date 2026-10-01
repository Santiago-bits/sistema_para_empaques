<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de negocio con mensaje apto para el usuario (p.ej. "El cajón ya está
 * asignado a otra carga"). No se registra como error técnico.
 */
class BusinessException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'business', public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
