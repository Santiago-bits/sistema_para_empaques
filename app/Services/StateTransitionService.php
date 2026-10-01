<?php

namespace App\Services;

use App\Exceptions\ConcurrencyException;
use App\Exceptions\InvalidTransitionException;
use App\Models\StateHistory;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia el estado de un registro validando la transición, de forma atómica
 * (UPDATE condicionado al estado y versión actuales) y guardando el historial.
 *
 * Si dos usuarios intentan la misma transición a la vez, sólo uno la aplica;
 * el otro recibe ConcurrencyException.
 */
class StateTransitionService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $extra  Columnas adicionales a actualizar en la misma sentencia.
     * @param  bool  $force  Permite una transición fuera del grafo (operación especial autorizada y auditada).
     */
    public function transition(
        Model $model,
        BackedEnum $to,
        ?string $notes = null,
        array $extra = [],
        bool $force = false,
        string $column = 'status',
    ): Model {
        /** @var BackedEnum $from */
        $from = $model->{$column};

        if (! $force && ! $from->canTransitionTo($to)) {
            throw new InvalidTransitionException($from->label(), $to->label());
        }

        return DB::transaction(function () use ($model, $from, $to, $notes, $extra, $force, $column) {
            $hasVersion = $this->hasVersion($model);
            $query = $model->newQuery()->whereKey($model->getKey())->where($column, $from->value);
            $values = array_merge($extra, [$column => $to->value, 'updated_at' => now()]);

            if ($hasVersion) {
                $query->where('version', $model->version);
                $values['version'] = DB::raw('version + 1');
            }

            if ($query->toBase()->update($this->prepare($model, $values)) !== 1) {
                throw new ConcurrencyException;
            }

            StateHistory::query()->create([
                'stateful_type' => $model->getMorphClass(),
                'stateful_id' => $model->getKey(),
                'from_state' => $from->value,
                'to_state' => $to->value,
                'user_id' => auth()->id(),
                'notes' => $notes,
                'created_at' => now(),
            ]);

            $this->audit->log(
                $force ? 'force_status' : 'status',
                $model,
                [$column => $from->value],
                array_merge([$column => $to->value], array_map(fn ($v) => $v instanceof BackedEnum ? $v->value : $v, $extra)),
                reason: $notes,
            );

            return $model->refresh();
        });
    }

    /** Registra el estado inicial de un registro recién creado. */
    public function recordInitial(Model $model, BackedEnum|string $state, ?string $notes = null): void
    {
        StateHistory::query()->create([
            'stateful_type' => $model->getMorphClass(),
            'stateful_id' => $model->getKey(),
            'from_state' => null,
            'to_state' => $state instanceof BackedEnum ? $state->value : $state,
            'user_id' => auth()->id(),
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    private function hasVersion(Model $model): bool
    {
        static $cache = [];

        return $cache[$model->getTable()] ??= Schema::hasColumn($model->getTable(), 'version');
    }

    private function prepare(Model $model, array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof BackedEnum) {
                $values[$key] = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $values[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $values;
    }
}
