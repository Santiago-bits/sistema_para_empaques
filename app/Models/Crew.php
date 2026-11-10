<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cuadrilla de trabajo (cosecha, empaque, carga). */
class Crew extends Model
{
    use BelongsToWarehouse;

    use Auditable, SoftDeletes;

    public const KINDS = ['harvest' => 'Cosecha', 'packing' => 'Empaque', 'loading' => 'Carga', 'other' => 'Otra'];

    protected $fillable = ['code', 'name', 'kind', 'leader', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
