<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DTV-e (Documento de Tránsito Vegetal electrónico de SENASA): ingreso o egreso de fruta, con una línea por
 * especie/variedad. Es el registro que antes se llevaba en la hoja «DTV-e» del Excel.
 */
class DtvDocument extends Model
{
    use Auditable, BelongsToWarehouse, SoftDeletes;

    public const DIRECTIONS = ['in' => 'Ingreso', 'out' => 'Egreso'];

    /** Tipos de movimiento habituales (origen-destino). Se puede escribir cualquier otro que use ARCA. */
    public const DOC_TYPES = [
        'EST-EMP' => 'EST-EMP', 'PROD-EMP' => 'PROD-EMP', 'EMP-CTC' => 'EMP-CTC', 'EMP-MER' => 'EMP-MER', 'EMP-MIN' => 'EMP-MIN',
        'EMP-EMP' => 'EMP-EMP', 'CTC-MER' => 'CTC-MER', 'CTC-MIN' => 'CTC-MIN', 'CTC-EMP' => 'CTC-EMP', 'OTRO' => 'Otro',
    ];

    /** Códigos de lugar que forman el tipo («EMP-CTC» = de empaque a centro de tratamiento cuarentenario). */
    public const PLACES = [
        'EMP' => 'EMP · Empaque',
        'CTC' => 'CTC · Centro de tratamiento cuarentenario',
        'EST' => 'EST · Establecimiento (finca)',
        'PROD' => 'PROD · Productor',
        'MER' => 'MER · Mercado',
        'MIN' => 'MIN',
    ];

    /** Estado del movimiento en ARCA. */
    public const ARCA_STATUSES = ['issued' => 'Emitido', 'closed' => 'Cerrado', 'voided' => 'Anulado'];

    protected $fillable = [
        'warehouse_id', 'date', 'direction', 'number', 'doc_type', 'issuer', 'establishment', 'recipient', 'destination',
        'transport', 'notes', 'load_id', 'lot_id', 'created_by', 'loaded_on', 'expires_on', 'arca_status', 'needs_intervention',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'loaded_on' => 'date', 'expires_on' => 'date', 'needs_intervention' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DtvLine::class)->orderBy('id');
    }

    public function relatedLoad(): BelongsTo
    {
        return $this->belongsTo(Load::class, 'load_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function directionLabel(): string
    {
        return self::DIRECTIONS[$this->direction] ?? $this->direction;
    }

    /** Kilos con signo: los egresos restan (como en la planilla). */
    public function signedKg(): float
    {
        $kg = (float) $this->lines->sum('kg_total');

        return $this->direction === 'out' ? -$kg : $kg;
    }
}
