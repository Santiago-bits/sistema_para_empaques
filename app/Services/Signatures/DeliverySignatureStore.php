<?php

namespace App\Services\Signatures;

/**
 * Almacenamiento de la firma de recepción de un remito.
 *
 * Hoy: firma manuscrita dibujada en canvas (PNG) → CanvasSignatureStore.
 * Futuro: firma digital con certificado (Ley 25.506) o firma electrónica de un proveedor externo.
 * Una nueva implementación sólo debe cumplir este contrato: recibir el payload enviado por el
 * cliente, validarlo y devolver la ruta privada (disco "local") donde quedó la evidencia.
 */
interface DeliverySignatureStore
{
    /**
     * Valida y guarda la firma. Lanza BusinessException si el contenido es inválido.
     *
     * @return string ruta relativa en el disco privado
     */
    public function store(string $payload): string;

    /** Elimina una firma guardada (p.ej. si la transacción de entrega falló). */
    public function delete(string $path): void;
}
