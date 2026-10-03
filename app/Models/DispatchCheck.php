<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchCheck extends Model
{
    use Auditable;

    public $timestamps = false;

    public const ITEMS = [
        'truck' => 'Camión correcto',
        'plate' => 'Patente correcta',
        'driver' => 'Camionero correcto',
        'quantity' => 'Cantidad correcta',
        'weight' => 'Peso correcto',
        'documentation' => 'Documentación',
        'remito' => 'Remito',
        'billing' => 'Facturación',
        'destination' => 'Destino',
    ];

    protected $fillable = [
        'load_id', 'item', 'checked', 'user_id', 'checked_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'checked' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function loadRecord(): BelongsTo
    {
        return $this->belongsTo(Load::class, 'load_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
