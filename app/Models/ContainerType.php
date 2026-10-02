<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tipo de envase: bin, caja de cartón, jaula… */
class ContainerType extends Model
{
    use Auditable, SoftDeletes;

    public const KINDS = ['box' => 'Caja', 'bin' => 'Bin', 'crate' => 'Jaula / cajón cosechero', 'other' => 'Otro'];

    protected $fillable = ['code', 'name', 'kind', 'tare_kg', 'capacity_kg', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'tare_kg' => 'decimal:2', 'capacity_kg' => 'decimal:2'];
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
