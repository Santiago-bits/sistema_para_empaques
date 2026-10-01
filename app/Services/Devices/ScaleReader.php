<?php

namespace App\Services\Devices;

/**
 * Lector de balanza. La pantalla de escaneo siempre permite carga manual del peso;
 * un lector sólo agrega el botón "Leer balanza".
 */
interface ScaleReader
{
    /** Nombre del driver (manual | api). */
    public function driver(): string;

    /**
     * Última lectura disponible para la estación, o null si no hay lectura.
     *
     * @return array{weight: float, unit: string, stable: bool, read_at: ?string, stale: bool}|null
     */
    public function read(string $station): ?array;
}
