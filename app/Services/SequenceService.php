<?php

namespace App\Services;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Numeraciones configurables (CJ-000001, CARG-000025, 0001-00001234...).
 * El número se toma con SELECT ... FOR UPDATE dentro de una transacción,
 * por lo que dos usuarios simultáneos nunca obtienen el mismo número.
 */
class SequenceService
{
    public const DEFAULTS = [
        'pallet' => ['PAL-', 6],
        'crate' => ['CJ-', 6],
        'lot' => ['LOT-', 5],
        'load' => ['CARG-', 5],
        'remito' => ['0001-', 8],
        'invoice' => ['', 8],
        'ticket' => ['TK-', 6],
        'incident' => ['INC-', 5],
    ];

    public function next(string $key): string
    {
        return DB::transaction(function () use ($key) {
            $sequence = Sequence::query()->where('key', $key)->lockForUpdate()->first();

            if (! $sequence) {
                [$prefix, $padding] = self::DEFAULTS[$key] ?? [strtoupper($key).'-', 6];
                Sequence::query()->insertOrIgnore([
                    'key' => $key, 'prefix' => $prefix, 'padding' => $padding, 'next_number' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $sequence = Sequence::query()->where('key', $key)->lockForUpdate()->firstOrFail();
            }

            $number = (int) $sequence->next_number;
            $sequence->forceFill(['next_number' => $number + 1])->save();

            return $this->format($sequence, $number);
        }, 5);
    }

    /** Vista previa del próximo número sin consumirlo. */
    public function peek(string $key): string
    {
        $sequence = Sequence::query()->where('key', $key)->first();
        if (! $sequence) {
            [$prefix, $padding] = self::DEFAULTS[$key] ?? [strtoupper($key).'-', 6];

            return $prefix.str_pad('1', $padding, '0', STR_PAD_LEFT);
        }

        return $this->format($sequence, (int) $sequence->next_number);
    }

    private function format(Sequence $sequence, int $number): string
    {
        return $sequence->prefix.str_pad((string) $number, (int) $sequence->padding, '0', STR_PAD_LEFT);
    }
}
