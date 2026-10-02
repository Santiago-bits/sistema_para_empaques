<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Packer extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'first_name', 'last_name', 'dni', 'shift_id', 'hired_on', 'active', 'notes', 'cuil', 'phone', 'address', 'birth_date',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'hired_on' => 'date',
            'birth_date' => 'date',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    public function crates(): HasMany
    {
        return $this->hasMany(Crate::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }
}
