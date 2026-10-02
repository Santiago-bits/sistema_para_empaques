<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Selección / categoría comercial de la fruta (Extra, Elegido, Comercial…). */
class Grade extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function crates(): HasMany
    {
        return $this->hasMany(Crate::class);
    }
}
