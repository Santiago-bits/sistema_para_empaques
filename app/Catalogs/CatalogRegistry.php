<?php

namespace App\Catalogs;

use InvalidArgumentException;

/**
 * Registro de catálogos administrables. Agregar un catálogo nuevo = crear su
 * definición y sumarla acá: rutas, listado, formulario, ficha e importación salen solos.
 */
class CatalogRegistry
{
    /** @var list<class-string<CatalogDefinition>> En el orden en que se muestran. */
    public const DEFINITIONS = [
        Definitions\ProducerDefinition::class,
        Definitions\OwnerDefinition::class,
        Definitions\ClientDefinition::class,
        Definitions\DestinationDefinition::class,
        Definitions\ProviderDefinition::class,
        Definitions\TransporterDefinition::class,
        Definitions\TruckDefinition::class,
        Definitions\DriverDefinition::class,
        Definitions\VarietyDefinition::class,
        Definitions\SizeDefinition::class,
        Definitions\GradeDefinition::class,
        Definitions\ContainerTypeDefinition::class,
        Definitions\PackerDefinition::class,
        Definitions\CrewDefinition::class,
        Definitions\EmployeeDefinition::class,
        Definitions\ShiftDefinition::class,
        Definitions\ProductionLineDefinition::class,
        Definitions\ProductionTargetDefinition::class,
        Definitions\ReasonDefinition::class,
        Definitions\SeasonDefinition::class,
    ];

    /** @var array<string, CatalogDefinition>|null */
    private static ?array $instances = null;

    /** @return array<string, CatalogDefinition> */
    public static function all(): array
    {
        if (self::$instances === null) {
            self::$instances = [];
            foreach (self::DEFINITIONS as $class) {
                $definition = new $class;
                self::$instances[$definition->key()] = $definition;
            }
        }

        return self::$instances;
    }

    public static function get(string $key): CatalogDefinition
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("Catálogo desconocido: {$key}");
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /** @return array<string, CatalogDefinition> Catálogos que admiten importación. */
    public static function importable(): array
    {
        return array_filter(self::all(), fn (CatalogDefinition $d) => $d->importColumns() !== []);
    }
}
