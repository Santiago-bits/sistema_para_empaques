<?php

namespace App\Services\Devices;

/**
 * Resuelve el driver de cada dispositivo según la configuración
 * (Configuración → Producción → balanza; config/galpon.php → devices).
 */
class DeviceManager
{
    public function scale(): ScaleReader
    {
        $driver = (string) (setting('production.scale_driver') ?: config('galpon.devices.scale_driver', 'manual'));

        return match ($driver) {
            'api' => new ApiScaleReader,
            default => new ManualScaleReader,
        };
    }

    public function labelPrinter(): LabelPrinter
    {
        return match ((string) config('galpon.devices.label_printer', 'browser')) {
            // 'zpl' => new ZplLabelPrinter, (pendiente, ver BrowserLabelPrinter)
            default => new BrowserLabelPrinter,
        };
    }
}
