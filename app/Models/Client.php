<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use Auditable, SoftDeletes;

    public const TAX_CONDITIONS = [
        'RI' => 'Responsable Inscripto',
        'MT' => 'Monotributo',
        'CF' => 'Consumidor Final',
        'EX' => 'Exento',
    ];

    protected $fillable = [
        'business_name', 'name', 'cuit', 'dni', 'address', 'locality', 'province', 'phone', 'email',
        'tax_condition', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(Destination::class);
    }

    public function loads(): HasMany
    {
        return $this->hasMany(Load::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
