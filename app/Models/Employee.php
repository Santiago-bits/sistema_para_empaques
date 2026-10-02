<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'code', 'first_name', 'last_name', 'dni', 'cuil', 'phone', 'position', 'crew_id', 'hired_on', 'daily_wage', 'notes', 'active',
        'address', 'birth_date', 'cbu', 'bank_alias',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'hired_on' => 'date', 'birth_date' => 'date', 'daily_wage' => 'decimal:2'];
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->last_name.', '.$this->first_name, ', ');
    }

    /** Mismo atributo que el resto de los titulares de cuenta corriente. */
    public function getNameAttribute(): string
    {
        return $this->full_name;
    }
}
