<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Asociación de citricultores: común a todo el sistema; cada galpón pertenece a una o a varias. */
class Association extends Model
{
    use Auditable;

    protected $fillable = ['name', 'short_name', 'cuit', 'locality', 'province', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\CurrentWarehouse::forgetAssociations());
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class)->withTimestamps();
    }
}
