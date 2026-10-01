<?php

namespace App\Services;

use App\Models\Season;
use Illuminate\Support\Facades\DB;

/** Garantiza que haya una sola temporada vigente a la vez. */
class SeasonService
{
    public function makeCurrent(Season $season): Season
    {
        return DB::transaction(function () use ($season) {
            // Bloquea todas las temporadas para serializar cambios simultáneos de vigencia.
            Season::query()->lockForUpdate()->get(['id']);

            Season::query()->where('is_current', true)->whereKeyNot($season->getKey())->get()
                ->each(fn (Season $other) => $other->update(['is_current' => false]));

            if (! $season->is_current) {
                $season->update(['is_current' => true]);
            }

            return $season;
        });
    }
}
