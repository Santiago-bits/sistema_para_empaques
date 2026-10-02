<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Cotización del dólar: una por día; cargarla de nuevo para el mismo día la corrige (queda auditado). */
class ExchangeRateService
{
    public function save(array $data, User $by): ExchangeRate
    {
        $date = Carbon::parse($data['date'])->startOfDay();
        $values = [
            'sell' => round((float) $data['sell'], 4),
            'buy' => isset($data['buy']) ? round((float) $data['buy'], 4) : null,
            'source' => $data['source'] ?? null,
            'user_id' => $by->id,
        ];

        return DB::transaction(function () use ($date, $values) {
            // whereDate: la columna puede guardarse con hora según el motor (SQLite / MySQL).
            $rate = ExchangeRate::query()->where('currency', 'USD')->whereDate('date', $date->toDateString())->lockForUpdate()->first();
            if ($rate) {
                $rate->update($values);

                return $rate;
            }

            return ExchangeRate::query()->create($values + ['date' => $date, 'currency' => 'USD']);
        });
    }
}
