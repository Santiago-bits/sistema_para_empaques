<?php

namespace App\Services\Devices;

/** Sin balanza conectada: el operador ingresa el peso a mano. */
class ManualScaleReader implements ScaleReader
{
    public function driver(): string
    {
        return 'manual';
    }

    public function read(string $station): ?array
    {
        return null;
    }
}
