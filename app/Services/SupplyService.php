<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\InventoryMovement;
use App\Models\Supply;
use Illuminate\Support\Facades\DB;

/**
 * Insumos: stock y movimientos (ingreso / egreso / ajuste) con bloqueo pesimista sobre el insumo,
 * de modo que dos egresos simultáneos nunca dejen el stock negativo.
 * Alerta de stock bajo (fingerprint stock_low:supply:{id}) que se resuelve sola al reponer.
 */
class SupplyService
{
    public function __construct(private readonly AlertService $alerts)
    {
    }

    public static function alertFingerprint(Supply $supply): string
    {
        return 'stock_low:supply:'.$supply->getKey();
    }

    /** Stock bajo: tiene mínimo definido y el stock es menor o igual. */
    public static function isLow(Supply $supply): bool
    {
        return (float) $supply->min_stock > 0 && (float) $supply->stock <= (float) $supply->min_stock;
    }

    /** Alta de insumo. El stock inicial se registra como movimiento de ingreso (trazabilidad). */
    public function create(array $data): Supply
    {
        $initial = (float) ($data['stock'] ?? 0);
        unset($data['stock']);

        $supply = DB::transaction(function () use ($data, $initial) {
            $supply = Supply::query()->create($data + ['stock' => 0]);
            if ($initial > 0) {
                $this->move($supply, 'in', $initial, ['notes' => 'Stock inicial', 'unit_cost' => $data['unit_cost'] ?? null]);
            }

            return $supply;
        });
        $this->syncLowStockAlert($supply->refresh());

        return $supply;
    }

    /** Modificación de datos del insumo (el stock sólo cambia con movimientos). */
    public function update(Supply $supply, array $data): Supply
    {
        unset($data['stock']);
        $supply->update($data);
        $this->syncLowStockAlert($supply->refresh());

        return $supply;
    }

    /**
     * Registra un movimiento de stock.
     *
     * - in: suma $quantity (> 0). Si trae unit_cost, actualiza el costo del insumo (último costo).
     * - out: resta $quantity (> 0). Nunca deja stock negativo: BusinessException.
     * - adjust: $quantity es el stock contado (>= 0); se guarda la diferencia (con signo) en quantity.
     *
     * $data: unit_cost, provider_id, reference, notes, moved_at.
     */
    public function move(Supply $supply, string $type, float $quantity, array $data = []): InventoryMovement
    {
        if (! array_key_exists($type, InventoryMovement::TYPES)) {
            throw new BusinessException('Tipo de movimiento inválido.');
        }
        if ($type === 'adjust' ? $quantity < 0 : $quantity <= 0) {
            throw new BusinessException($type === 'adjust' ? 'El stock contado no puede ser negativo.' : 'La cantidad debe ser mayor a cero.');
        }

        $movement = DB::transaction(function () use ($supply, $type, $quantity, $data) {
            /** @var Supply $locked */
            $locked = Supply::query()->whereKey($supply->getKey())->lockForUpdate()->first();
            if (! $locked) {
                throw new BusinessException('El insumo no existe.');
            }

            $current = round((float) $locked->stock, 2);
            $quantity = round($quantity, 2);

            [$delta, $after] = match ($type) {
                'in' => [$quantity, $current + $quantity],
                'out' => [-$quantity, $current - $quantity],
                'adjust' => [$quantity - $current, $quantity],
            };

            if ($type === 'out' && $after < 0) {
                throw new BusinessException(sprintf(
                    'Stock insuficiente de %s: disponible %s %s, se intentó retirar %s %s.',
                    $locked->name, num($current, 2), $locked->unit, num($quantity, 2), $locked->unit
                ));
            }
            if ($type === 'adjust' && abs($delta) < 0.005) {
                throw new BusinessException('El stock contado coincide con el actual: no hay nada que ajustar.');
            }

            $unitCost = isset($data['unit_cost']) && $data['unit_cost'] !== '' ? (float) $data['unit_cost'] : null;

            $movement = InventoryMovement::query()->create([
                'supply_id' => $locked->id,
                'type' => $type,
                'quantity' => $type === 'adjust' ? round($delta, 2) : $quantity,
                'stock_after' => round($after, 2),
                'unit_cost' => $unitCost,
                'provider_id' => $data['provider_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => auth()->id(),
                'moved_at' => $data['moved_at'] ?? now(),
            ]);

            $values = ['stock' => round($after, 2), 'updated_at' => now()];
            if ($type === 'in' && $unitCost !== null) {
                $values['unit_cost'] = $unitCost;
            }
            Supply::query()->whereKey($locked->id)->update($values);

            return $movement;
        });

        $supply->refresh();
        $this->syncLowStockAlert($supply);

        return $movement;
    }

    /** Crea/reabre la alerta de stock bajo o la resuelve si el stock volvió a superar el mínimo. */
    public function syncLowStockAlert(Supply $supply): void
    {
        $fingerprint = self::alertFingerprint($supply);

        if ($supply->active && self::isLow($supply)) {
            $this->alerts->raise(
                'stock_low',
                "Stock bajo: {$supply->name}",
                sprintf('Stock actual %s %s (mínimo %s %s).', num($supply->stock, 2), $supply->unit, num($supply->min_stock, 2), $supply->unit),
                $supply,
                'warning',
                $fingerprint,
            );

            return;
        }

        $this->alerts->resolveByFingerprint($fingerprint);
    }

    /** Cantidad de insumos activos con stock bajo (dashboard). */
    public function lowStockCount(): int
    {
        return Supply::query()->where('active', true)->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock')->count();
    }
}
