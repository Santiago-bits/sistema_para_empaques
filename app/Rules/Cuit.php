<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CUIT/CUIL argentino: 11 dígitos, prefijo válido y dígito verificador (módulo 11).
 * Acepta el valor con o sin guiones/puntos; normalizar con Cuit::normalize() antes de guardar.
 */
class Cuit implements ValidationRule
{
    private const PREFIXES = ['20', '23', '24', '25', '26', '27', '30', '33', '34'];

    private const WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! self::isValid((string) $value)) {
            $fail('El :attribute no es un CUIT válido (verificá los 11 dígitos y el dígito verificador).');
        }
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' ? null : $digits;
    }

    public static function isValid(string $value): bool
    {
        $digits = self::normalize($value) ?? '';

        if (strlen($digits) !== 11 || ! in_array(substr($digits, 0, 2), self::PREFIXES, true)) {
            return false;
        }

        $sum = 0;
        foreach (self::WEIGHTS as $i => $weight) {
            $sum += (int) $digits[$i] * $weight;
        }

        $check = 11 - ($sum % 11);
        $check = match ($check) {
            11 => 0,
            10 => -1, // No existe un CUIT válido con verificador 10.
            default => $check,
        };

        return $check === (int) $digits[10];
    }

    /** 20123456786 → 20-12345678-6 */
    public static function format(?string $value): ?string
    {
        $digits = self::normalize($value);
        if ($digits === null || strlen($digits) !== 11) {
            return $value;
        }

        return substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10);
    }
}
