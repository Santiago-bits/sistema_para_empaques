<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Movimiento de cuenta corriente. Saldo = Σ debe − Σ haber, desde el punto de vista del galpón:
 * positivo → el titular nos debe; negativo → le debemos. Nunca se edita: se anula con motivo.
 */
class AccountMovement extends Model
{
    use Auditable, BelongsToWarehouse;

    /** Titulares de cuenta: clave del morph map => [singular, plural]. */
    public const HOLDERS = [
        'client' => ['Cliente', 'Clientes'],
        'producer' => ['Productor', 'Productores'],
        'transporter' => ['Transportista', 'Transportistas'],
        'provider' => ['Proveedor', 'Proveedores'],
        'employee' => ['Empleado', 'Empleados'],
    ];

    public const TYPES = [
        'opening' => 'Saldo inicial',
        'invoice' => 'Factura',
        'credit_note' => 'Nota de crédito',
        'purchase' => 'Compra de fruta',
        'association_fee' => 'Tasa de asociación',
        'freight' => 'Flete',
        'collection' => 'Cobro',
        'payment' => 'Pago',
        'advance' => 'Adelanto / anticipo',
        'check_rejected' => 'Cheque rechazado',
        'debit_note' => 'Cargo / nota de débito',
        'credit_adjustment' => 'Ajuste a favor del titular',
    ];

    public const METHODS = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'check' => 'Cheque', 'other' => 'Otro'];

    protected $fillable = [
        'warehouse_id', 'holder_type', 'holder_id', 'date', 'type', 'description', 'debit', 'credit', 'payment_method',
        'reference', 'source_type', 'source_id', 'user_id', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2', 'voided_at' => 'datetime'];
    }

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** @return class-string<Model> */
    public static function holderClass(string $type): string
    {
        abort_unless(array_key_exists($type, self::HOLDERS), 404);

        return Model::getActualClassNameForMorph($type);
    }

    public static function holderLabel(Model $holder): string
    {
        return (string) ($holder->getAttribute('business_name') ?? $holder->getAttribute('name') ?? '#'.$holder->getKey());
    }
}
