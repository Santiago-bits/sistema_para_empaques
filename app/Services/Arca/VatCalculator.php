<?php

namespace App\Services\Arca;

/**
 * Cálculo único de importes de un comprobante, en CENTAVOS (enteros) para no acumular
 * errores de redondeo de punto flotante. El IVA se calcula por alícuota sobre la base
 * agrupada (como lo valida ARCA en AlicIva: Importe = BaseImp × alícuota), no ítem por ítem.
 * Lo usan el guardado del comprobante y el envío a ARCA: ambos dan siempre lo mismo.
 */
final class VatCalculator
{
    /** Subtotal de un ítem en centavos: cantidad (2 decimales) × precio (4 decimales). */
    public static function lineCents(float|string $quantity, float|string $unitPrice): int
    {
        $q = (int) round((float) $quantity * 100);      // centésimos
        $p = (int) round((float) $unitPrice * 10000);   // diezmilésimos

        return (int) round($q * $p / 10000);             // centavos
    }

    /**
     * @param  iterable<array{subtotal_cents: int, vat_rate: float|string}>  $lines
     * @return array<string, array{base: int, tax: int}> alícuota normalizada ('21', '10.5', '0') => centavos
     */
    public static function groups(iterable $lines): array
    {
        $groups = [];
        foreach ($lines as $line) {
            $rate = self::rateKey($line['vat_rate']);
            $groups[$rate]['base'] = ($groups[$rate]['base'] ?? 0) + (int) $line['subtotal_cents'];
        }
        foreach ($groups as $rate => $g) {
            $groups[$rate]['tax'] = (int) round($g['base'] * (float) $rate / 100);
        }
        ksort($groups);

        return $groups;
    }

    /** @return array{net: int, vat: int, total: int} en centavos */
    public static function totals(array $groups): array
    {
        $net = array_sum(array_column($groups, 'base'));
        $vat = array_sum(array_column($groups, 'tax'));

        return ['net' => $net, 'vat' => $vat, 'total' => $net + $vat];
    }

    public static function rateKey(float|string $rate): string
    {
        return rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.');
    }

    public static function toAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
