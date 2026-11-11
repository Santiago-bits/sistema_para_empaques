<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tipo de envase: bin, caja de cartón, jaula… */
class ContainerType extends Model
{
    use BelongsToWarehouse;

    use Auditable, SoftDeletes;

    public const KINDS = ['box' => 'Caja', 'bin' => 'Bin', 'crate' => 'Jaula / cajón cosechero', 'other' => 'Otro'];

    protected $fillable = ['code', 'name', 'kind', 'tare_kg', 'capacity_kg', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'tare_kg' => 'decimal:2', 'capacity_kg' => 'decimal:2'];
    }

    /** Envase con el que se dan de alta los cajones si no se elige otro (Configuración → Producción). */
    public static function defaultForCrates(): ?int
    {
        $id = (int) setting('production.default_container_type_id', 0);
        if ($id && self::query()->whereKey($id)->where('active', true)->exists()) {
            return $id;
        }

        return self::query()->where('active', true)->where('kind', '!=', 'bin')->where('name', 'like', '%madera%')->orderBy('id')->value('id');
    }

    public function crates(): HasMany
    {
        return $this->hasMany(Crate::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
