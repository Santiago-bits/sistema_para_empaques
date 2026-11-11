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
                // insertOrIgnore no pasa por el modelo: el galpón se pone a mano (cada galpón tiene su numeración).
                Sequence::query()->insertOrIgnore([
                    ...(\App\Support\Tenancy::ready() ? ['warehouse_id' => \App\Support\CurrentWarehouse::id()] : []),
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

    /** Prefijo y cantidad de dígitos de una numeración (la del galpón, o la de fábrica si todavía no se usó). */
    public function shape(string $key): array
    {
        $sequence = Sequence::query()->where('key', $key)->first();

        return $sequence ? [(string) $sequence->prefix, (int) $sequence->padding] : (self::DEFAULTS[$key] ?? [strtoupper($key).'-', 6]);
    }

    /**
     * Arma el código completo a partir de lo que se escribió: «101», «CJ-101» o «cj-00101» → «CJ-000101».
     * Si no tiene números (o es de otro formato), se devuelve tal cual.
     */
    public function normalize(string $key, string $value): string
    {
        $value = trim($value);
        [$prefix, $padding] = $this->shape($key);
        if (preg_match('/^(.*?)(\d+)$/', $value, $m) && ($m[1] === '' || strcasecmp($m[1], $prefix) === 0)) {
            return $prefix.str_pad(ltrim($m[2], '0') ?: '0', $padding, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    /** El número de un código («CJ-000123» → 123), o null si no es de esta numeración. */
    public function numberOf(string $key, ?string $code): ?int
    {
        [$prefix] = $this->shape($key);
        if ($code === null || ! preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/i', trim($code), $m)) {
            return null;
        }

        return (int) $m[1];
    }

    private function format(Sequence $sequence, int $number): string
    {
        return $sequence->prefix.str_pad((string) $number, (int) $sequence->padding, '0', STR_PAD_LEFT);
    }
}
