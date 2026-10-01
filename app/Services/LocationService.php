<?php

namespace App\Services;

use App\Enums\PalletStatus;
use App\Exceptions\BusinessException;
use App\Models\Crate;
use App\Models\LocationMovement;
use App\Models\Pallet;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Support\CurrentWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ubicaciones físicas: movimientos de pallets/cajones, ocupación y capacidad.
 */
class LocationService
{
    /** Estados de pallet que ocupan lugar físico en el galpón. */
    public const PALLET_PRESENT = ['empty', 'received', 'with_product', 'reserved', 'loaded'];

    /** Estados de cajón que ya no se mueven (salieron o se anularon). */
    private const CRATE_GONE = ['dispatched', 'invoiced', 'voided'];

    public function __construct(private readonly AuditService $audit)
    {
    }

    /**
     * Mueve un pallet o cajón a una ubicación (o a un destino externo descripto por $toLabel, p.ej. "Camión AB123CD").
     * Bloquea el registro (lockForUpdate), registra el movimiento, actualiza location_id y audita.
     * Mover un pallet mueve también sus cajones.
     */
    public function move(Model $movable, ?WarehouseLocation $to, ?string $toLabel = null, ?string $notes = null): LocationMovement
    {
        if (! $movable instanceof Pallet && ! $movable instanceof Crate) {
            throw new \InvalidArgumentException('Sólo se pueden mover pallets o cajones.');
        }
        $toLabel = $toLabel !== null ? trim($toLabel) : null;
        if (! $to && ($toLabel === null || $toLabel === '')) {
            throw new BusinessException('Indicá la ubicación de destino.');
        }

        return DB::transaction(function () use ($movable, $to, $toLabel, $notes) {
            /** @var Pallet|Crate $item */
            $item = $movable->newQuery()->whereKey($movable->getKey())->lockForUpdate()->first();
            if (! $item) {
                throw new BusinessException('El registro a mover no existe.');
            }

            $isPallet = $item instanceof Pallet;
            $name = ($isPallet ? 'Pallet ' : 'Cajón ').$item->code;
            $status = $item->status->value;

            if ($isPallet && in_array($status, ['dispatched', 'voided'], true)) {
                throw new BusinessException("{$name} está «{$item->status->label()}»: no se puede mover.");
            }
            if (! $isPallet && in_array($status, self::CRATE_GONE, true)) {
                throw new BusinessException("{$name} está «{$item->status->label()}»: no se puede mover.");
            }

            if ($to) {
                $to = WarehouseLocation::query()->whereKey($to->getKey())->lockForUpdate()->first();
                if (! $to || ! $to->active) {
                    throw new BusinessException('La ubicación de destino no existe o está inactiva.');
                }
                if ((int) $to->warehouse_id !== (int) $item->warehouse_id) {
                    throw new BusinessException('La ubicación de destino pertenece a otro galpón.');
                }
                if ((int) $item->location_id === (int) $to->id) {
                    throw new BusinessException("{$name} ya está en {$to->name}.");
                }
                if ($isPallet && $to->capacity_pallets > 0) {
                    $occupied = Pallet::query()->where('location_id', $to->id)->whereIn('status', self::PALLET_PRESENT)->count();
                    if ($occupied >= $to->capacity_pallets) {
                        throw new BusinessException("La ubicación {$to->name} está completa ({$occupied}/{$to->capacity_pallets} pallets).");
                    }
                }
            }

            $fromId = $item->location_id;
            $movement = LocationMovement::query()->create([
                'movable_type' => $item->getMorphClass(),
                'movable_id' => $item->getKey(),
                'from_location_id' => $fromId,
                'to_location_id' => $to?->id,
                'to_label' => $toLabel ?: null,
                'user_id' => auth()->id(),
                'notes' => $notes,
                'moved_at' => now(),
            ]);

            $item->newQuery()->whereKey($item->getKey())->update(['location_id' => $to?->id, 'updated_at' => now()]);

            $cratesMoved = 0;
            if ($isPallet) {
                $cratesMoved = Crate::query()->where('pallet_id', $item->id)
                    ->whereNotIn('status', self::CRATE_GONE)
                    ->update(['location_id' => $to?->id, 'updated_at' => now()]);
            }

            $fromName = $fromId ? WarehouseLocation::query()->whereKey($fromId)->value('name') : 'Sin ubicación';
            $toName = $to?->name ?? $toLabel;
            $this->audit->log('move', $item, ['location_id' => $fromId], array_filter([
                'location_id' => $to?->id,
                'to_label' => $toLabel ?: null,
                'crates_moved' => $isPallet ? $cratesMoved : null,
            ], fn ($v) => $v !== null), "{$name}: {$fromName} → {$toName}", $notes);

            $movable->setAttribute('location_id', $to?->id);

            return $movement;
        });
    }

    /**
     * Busca un pallet o cajón por código / código de barras.
     *
     * @return array{type: string, model: Pallet|Crate}|null
     */
    public function findMovable(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $pallet = Pallet::query()->where('code', $code)->orWhere('barcode', $code)->first();
        if ($pallet) {
            return ['type' => 'pallet', 'model' => $pallet];
        }
        $crate = Crate::query()->where('code', $code)->orWhere('barcode', $code)->first();

        return $crate ? ['type' => 'crate', 'model' => $crate] : null;
    }

    public function findLocation(string $code, ?int $warehouseId = null): ?WarehouseLocation
    {
        return WarehouseLocation::query()
            ->where('warehouse_id', $warehouseId ?? CurrentWarehouse::id())
            ->where('code', trim($code))
            ->first();
    }

    /**
     * Capacidad del galpón: capacidad total (warehouses.capacity_pallets o, si es 0, suma de ubicaciones),
     * pallets presentes y espacios libres.
     *
     * @return array{capacity: int, pallets: int, free: int, occupancy_pct: float, located: int, unlocated: int}
     */
    public function capacitySummary(?int $warehouseId = null): array
    {
        $warehouseId ??= CurrentWarehouse::id();
        $capacity = (int) Warehouse::query()->whereKey($warehouseId)->value('capacity_pallets');
        if ($capacity <= 0) {
            $capacity = $this->locationsCapacity($warehouseId);
        }

        $present = Pallet::query()->where('warehouse_id', $warehouseId)->whereIn('status', self::PALLET_PRESENT);
        $pallets = (clone $present)->count();
        $located = (clone $present)->whereNotNull('location_id')->count();

        return [
            'capacity' => $capacity,
            'pallets' => $pallets,
            'free' => max(0, $capacity - $pallets),
            'occupancy_pct' => $capacity > 0 ? round($pallets / $capacity * 100, 1) : 0.0,
            'located' => $located,
            'unlocated' => $pallets - $located,
        ];
    }

    /**
     * Inventario de pallets por estado.
     *
     * @return array<string, array{label: string, color: string, count: int}>
     */
    public function palletInventory(?int $warehouseId = null): array
    {
        $warehouseId ??= CurrentWarehouse::id();
        $counts = Pallet::query()->where('warehouse_id', $warehouseId)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $result = [];
        foreach (PalletStatus::cases() as $status) {
            $result[$status->value] = ['label' => $status->label(), 'color' => $status->color(), 'count' => (int) ($counts[$status->value] ?? 0)];
        }

        return $result;
    }

    /**
     * Ocupación por ubicación, incluyendo descendientes.
     * direct: pallets en la ubicación; total: incluye sububicaciones; capacity: propia o suma de hijas;
     * crates: cajones sueltos (sin pallet) en la ubicación y sus hijas.
     *
     * @return array<int, array{direct: int, total: int, capacity: int, crates: int, free: int, pct: float}>
     */
    public function occupancy(?int $warehouseId = null): array
    {
        $warehouseId ??= CurrentWarehouse::id();
        $locations = WarehouseLocation::query()->where('warehouse_id', $warehouseId)->get(['id', 'parent_id', 'capacity_pallets']);
        $pallets = Pallet::query()->where('warehouse_id', $warehouseId)->whereIn('status', self::PALLET_PRESENT)
            ->whereNotNull('location_id')->selectRaw('location_id, COUNT(*) as total')->groupBy('location_id')->pluck('total', 'location_id');
        $crates = Crate::query()->where('warehouse_id', $warehouseId)->whereNull('pallet_id')
            ->whereNotIn('status', self::CRATE_GONE)->whereNotNull('location_id')
            ->selectRaw('location_id, COUNT(*) as total')->groupBy('location_id')->pluck('total', 'location_id');

        $children = $locations->groupBy('parent_id');
        $result = [];
        $compute = function (WarehouseLocation $loc, int $depth = 0) use (&$compute, &$result, $children, $pallets, $crates) {
            if (isset($result[$loc->id])) {
                return $result[$loc->id];
            }
            $direct = (int) ($pallets[$loc->id] ?? 0);
            $total = $direct;
            $looseCrates = (int) ($crates[$loc->id] ?? 0);
            $childCapacity = 0;
            if ($depth < 20) {
                foreach ($children->get($loc->id, collect()) as $child) {
                    $c = $compute($child, $depth + 1);
                    $total += $c['total'];
                    $looseCrates += $c['crates'];
                    $childCapacity += $c['capacity'];
                }
            }
            $capacity = (int) $loc->capacity_pallets > 0 ? (int) $loc->capacity_pallets : $childCapacity;

            return $result[$loc->id] = [
                'direct' => $direct,
                'total' => $total,
                'capacity' => $capacity,
                'crates' => $looseCrates,
                'free' => max(0, $capacity - $total),
                'pct' => $capacity > 0 ? round($total / $capacity * 100, 1) : 0.0,
            ];
        };
        foreach ($locations as $loc) {
            $compute($loc);
        }

        return $result;
    }

    /** IDs de la ubicación y todas sus descendientes. */
    public function descendantIds(WarehouseLocation $location): Collection
    {
        $all = WarehouseLocation::query()->where('warehouse_id', $location->warehouse_id)->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = collect([$location->id]);
        $queue = [$location->id];
        while ($queue) {
            $id = array_shift($queue);
            foreach ($all->get($id, collect()) as $child) {
                if (! $ids->contains($child->id)) {
                    $ids->push($child->id);
                    $queue[] = $child->id;
                }
            }
        }

        return $ids;
    }

    /** Capacidad sumando ubicaciones "hoja" con capacidad (evita contar dos veces sector y posiciones). */
    private function locationsCapacity(int $warehouseId): int
    {
        $occupancy = $this->occupancy($warehouseId);
        $roots = WarehouseLocation::query()->where('warehouse_id', $warehouseId)->whereNull('parent_id')->where('active', true)->pluck('id');

        return (int) $roots->sum(fn ($id) => $occupancy[$id]['capacity'] ?? 0);
    }
}
