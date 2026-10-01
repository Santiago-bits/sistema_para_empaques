<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Shift extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'starts_at', 'ends_at', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function packers(): HasMany
    {
        return $this->hasMany(Packer::class);
    }

    /** Devuelve el turno activo que corresponde a la hora dada (soporta turnos que cruzan medianoche). */
    public static function forTime(?Carbon $at = null): ?self
    {
        $time = ($at ?? now())->format('H:i:s');

        return static::query()->where('active', true)->get()->first(function (self $shift) use ($time) {
            $start = $shift->starts_at;
            $end = $shift->ends_at;

            return $start <= $end ? ($time >= $start && $time < $end) : ($time >= $start || $time < $end);
        });
    }
}
