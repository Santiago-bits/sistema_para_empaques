<?php

namespace App\Services;

use App\Models\SupplyYield;
use App\Models\User;

/** Rendimiento de cera e insumos: se abre un tambor/lote de insumo y al terminarlo se cierra con la fecha final. */
class SupplyYieldService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function save(?SupplyYield $yield, array $data, User $by, ?string $reason = null): SupplyYield
    {
        $creating = $yield === null;
        $yield ??= new SupplyYield(['created_by' => $by->id]);
        $before = $creating ? null : $yield->only(array_keys($data));
        $yield->fill($data)->save();
        $this->audit->log($creating ? 'create' : 'update', $yield, $before, $yield->only(array_keys($data)),
            ($creating ? 'Registró' : 'Corrigió').' el rendimiento de «'.$yield->name.'»', $reason);

        return $yield;
    }

    public function close(SupplyYield $yield, string $endedOn, ?float $quantityUsed, User $by): SupplyYield
    {
        return $this->save($yield, array_filter(['ended_on' => $endedOn, 'quantity_used' => $quantityUsed], fn ($v) => $v !== null), $by);
    }
}
