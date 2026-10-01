<?php

namespace App\Services;

use App\Catalogs\CatalogDefinition;
use App\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Altas, modificaciones, activación y baja lógica de catálogos.
 * La auditoría (valor anterior/nuevo) la registra el trait Auditable de cada modelo.
 */
class CatalogService
{
    public function save(CatalogDefinition $definition, Model $record, array $data): Model
    {
        return DB::transaction(function () use ($definition, $record, $data) {
            $created = ! $record->exists;
            $record->fill($data)->save();
            $definition->afterSave($record, $created);

            return $record;
        });
    }

    /** @return string mensaje para el usuario */
    public function toggle(CatalogDefinition $definition, Model $record): string
    {
        if (! $definition->hasToggle()) {
            throw new BusinessException('Este catálogo no admite activar/desactivar.');
        }

        return DB::transaction(function () use ($definition, $record) {
            $fresh = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());

            return $definition->toggle($fresh);
        });
    }

    public function delete(CatalogDefinition $definition, Model $record): void
    {
        if (! $definition->canDelete()) {
            throw new BusinessException('Este registro no se puede eliminar; desactivalo en su lugar.');
        }

        DB::transaction(function () use ($definition, $record) {
            if ($definition->inUse($record)) {
                throw new BusinessException('No se puede eliminar porque tiene registros asociados. Desactivalo en su lugar.');
            }
            $record->delete();
        });
    }
}
