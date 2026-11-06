<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Movimiento de envases (bines, jaulas, cajas) con un titular. Saldo = Σ entregados − Σ recibidos:
 * positivo → el titular tiene envases nuestros; negativo → le debemos envases. Nunca se edita: se anula.
 */
class ContainerMovement extends Model
{
    use Auditable, BelongsToWarehouse;

    /** Titulares: clave del morph map => [singular, plural]. Los camioneros no tienen cuenta corriente. */
    public const HOLDERS = AccountMovement::HOLDERS + ['driver' => ['Camionero', 'Camioneros']];

    public const DIRECTIONS = ['delivered' => 'Se los entregamos', 'received' => 'Nos los entregó'];

    public const REASONS = ['loan' => 'Préstamo o devolución', 'payment' => 'Como parte de pago'];

    protected $fillable = [
        'warehouse_id', 'holder_type', 'holder_id', 'container_type_id', 'date', 'direction', 'quantity', 'reason',
        'unit_price', 'amount', 'account_movement_id', 'description', 'user_id', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date', 'quantity' => 'integer', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class)->withTrashed();
    }

    public function accountMovement(): BelongsTo
    {
        return $this->belongsTo(AccountMovement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function scopeOf(Builder $query, Model $holder): Builder
    {
        return $query->where('holder_type', $holder->getMorphClass())->where('holder_id', $holder->getKey());
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** Cantidad con signo: + entregados al titular, − recibidos de él. */
    public function signedQuantity(): int
    {
        return $this->direction === 'delivered' ? $this->quantity : -$this->quantity;
    }

    /** @return class-string<Model> */
    public static function holderClass(string $type): string
    {
        abort_unless(array_key_exists($type, self::HOLDERS), 404);

        return Model::getActualClassNameForMorph($type);
    }
}
