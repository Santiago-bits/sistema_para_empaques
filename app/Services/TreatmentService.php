<?php

namespace App\Services;

use App\Models\Treatment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Tratamientos de la fruta (cuarentenarios, etc.): alta, corrección con motivo y resúmenes. */
class TreatmentService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function save(?Treatment $treatment, array $data, User $by, ?string $reason = null): Treatment
    {
        $creating = $treatment === null;
        $treatment ??= new Treatment(['created_by' => $by->id]);
        $before = $creating ? null : $treatment->only(array_keys($data));
        $treatment->fill($data)->save();
        $this->audit->log($creating ? 'create' : 'update', $treatment, $before, $treatment->only(array_keys($data)),
            ($creating ? 'Registró' : 'Corrigió').' un tratamiento ('.$treatment->type.', '.$treatment->quantity.' '.$treatment->unit.')', $reason);

        return $treatment;
    }

    public function delete(Treatment $treatment, string $reason): void
    {
        $treatment->delete();
        $this->audit->log('delete', $treatment, null, null, 'Eliminó un tratamiento del '.$treatment->date?->format('d/m/Y'), $reason);
    }

    /** Totales por tipo de tratamiento en el período filtrado. */
    public function totalsByType($query)
    {
        return (clone $query)->reorder()->toBase()->selectRaw('type, COUNT(*) as n, SUM(quantity) as qty')->groupBy('type')->orderBy('type')->get();
    }
}
