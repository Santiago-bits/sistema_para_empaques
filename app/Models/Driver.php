<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'dni', 'license_number', 'license_expires_on', 'transporter_id', 'phone',
        'active', 'cuil', 'email', 'address', 'locality', 'birth_date', 'license_category', 'emergency_contact', 'emergency_phone', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'license_expires_on' => 'date',
            'birth_date' => 'date',
        ];
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function loads(): HasMany
    {
        return $this->hasMany(Load::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    public function licenseExpired(): bool
    {
        return $this->license_expires_on !== null && $this->license_expires_on->isPast();
    }
}
