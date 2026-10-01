<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Funcionalidades activables. Un módulo desactivado no aparece en el menú,
 * no es accesible por URL (middleware `module:`) y sus permisos quedan bloqueados,
 * pero sus datos se conservan intactos para cuando se reactive.
 */
class ModuleService
{
    private const CACHE_KEY = 'galpon.modules';

    /** Catálogo de módulos: key => [nombre, descripción, habilitado por defecto, núcleo]. */
    public const CATALOG = [
        'core' => ['Núcleo', 'Usuarios, roles, configuración y auditoría', true, true],
        'catalogs' => ['Catálogos', 'Productores, propietarios, clientes, variedades, tamaños, embaladores', true, true],
        'pallets' => ['Gestión de pallets', 'Ingreso de pallets y lotes', true, false],
        'crates' => ['Gestión de cajones', 'Cajones, códigos y trazabilidad', true, false],
        'production' => ['Producción', 'Modo escaneo, producción, turnos y paradas', true, false],
        'quality' => ['Calidad', 'Controles de calidad, rechazos y merma', true, false],
        'locations' => ['Ubicaciones', 'Inventario físico, mapa del galpón y movimientos', true, false],
        'loads' => ['Cargas', 'Armado de cargas, cierre y despacho', true, false],
        'remitos' => ['Remitos', 'Remitos, QR y entregas', true, false],
        'documents' => ['Documentación', 'Adjuntos asociados a entidades', true, false],
        'reports' => ['Reportes', 'Informes, estadísticas y exportaciones', true, false],
        'billing' => ['Facturación', 'Comprobantes y clientes', true, false],
        'arca' => ['ARCA', 'Integración con ARCA (ex AFIP) para CAE', true, false],
        'supplies' => ['Insumos', 'Stock de materiales e inventario', true, false],
        'incidents' => ['Incidentes', 'Registro y seguimiento de incidentes', true, false],
        'maintenance' => ['Mantenimiento', 'Maquinaria y mantenimientos', false, false],
        'cold_rooms' => ['Cámaras frigoríficas', 'Temperatura y humedad', false, false],
        'costs' => ['Costos', 'Costos y rentabilidad', false, false],
        'client_portal' => ['Portal de clientes', 'Acceso de propietarios/clientes a su información', false, false],
        'whatsapp' => ['WhatsApp', 'Notificaciones por WhatsApp (integración futura)', false, false],
        'mobile_app' => ['Aplicación móvil', 'API para aplicación móvil', false, false],
    ];

    private ?array $states = null;

    public function states(): array
    {
        if ($this->states !== null) {
            return $this->states;
        }

        $stored = [];
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('modules')) {
                    return [];
                }

                return Module::query()->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all();
            });
        } catch (\Throwable) {
            // Sin base de datos: se usan los valores por defecto del catálogo.
        }

        $defaults = array_map(fn ($m) => $m[2], self::CATALOG);

        return $this->states = array_replace($defaults, $stored);
    }

    public function enabled(string $key): bool
    {
        if (isset(self::CATALOG[$key]) && self::CATALOG[$key][3]) {
            return true;
        }

        return $this->states()[$key] ?? false;
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        if (self::CATALOG[$key][3] ?? false) {
            return; // Los módulos núcleo no se pueden desactivar.
        }

        $module = Module::query()->where('key', $key)->firstOrFail();
        $module->update(['enabled' => $enabled]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->states = null;
    }
}
