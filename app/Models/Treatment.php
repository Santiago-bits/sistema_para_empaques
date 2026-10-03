<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tratamiento de la fruta (por ejemplo, el cuarentenario para entrar a la Patagonia): fecha, destino, cantidad,
 * tipo de tratamiento y empresa que lo hace. Antes: hoja «TRATAMIENTO» del Excel.
 */
class Treatment extends Model
{
    use Auditable, SoftDeletes;

    /** Tipos sugeridos (se puede escribir otro). Se configuran en Configuración → Tratamientos. */
    public const DEFAULT_TYPES = ['Tratamiento en frío', 'Bromuro de metilo'];

    protected $fillable = ['date', 'client_id', 'destination', 'quantity', 'unit', 'type', 'provider', 'load_id', 'dtv_number', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'quantity' => 'decimal:2'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function relatedLoad(): BelongsTo
    {
        return $this->belongsTo(Load::class, 'load_id');
    }

    /** @return list<string> */
    public static function types(): array
    {
        $configured = array_values(array_filter(array_map('trim', (array) setting('treatments.types', self::DEFAULT_TYPES))));

        return $configured !== [] ? $configured : self::DEFAULT_TYPES;
    }
}
