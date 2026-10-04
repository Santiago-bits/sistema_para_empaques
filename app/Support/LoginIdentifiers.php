<?php

namespace App\Support;

use App\Models\User;

/**
 * Identificadores con los que un usuario puede ingresar (Configuración → Seguridad):
 * usuario, DNI, CUIT, código interno o email. Lo usan el login y la recuperación de contraseña.
 */
class LoginIdentifiers
{
    public const ALLOWED = ['username', 'dni', 'cuit', 'internal_code', 'email'];

    public const LABELS = ['username' => 'usuario', 'dni' => 'DNI', 'cuit' => 'CUIT', 'internal_code' => 'código interno', 'email' => 'email'];

    /** @return list<string> */
    public static function enabled(): array
    {
        return array_values(array_intersect(self::ALLOWED, (array) setting('login.identifiers', ['username'])));
    }

    /** Texto de ayuda: "usuario, DNI o email". */
    public static function hint(): string
    {
        return collect(self::enabled() ?: ['username'])->map(fn ($i) => self::LABELS[$i])->join(', ', ' o ');
    }

    /** Etiqueta del campo de ingreso: "Usuario, DNI o email" (dice exactamente qué se puede escribir). */
    public static function label(): string
    {
        $hint = self::hint();

        return mb_strtoupper(mb_substr($hint, 0, 1)).mb_substr($hint, 1);
    }

    public static function find(string $login): ?User
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        $identifiers = self::enabled() ?: ['username'];
        $normalized = preg_replace('/[\s.\-]/', '', $login);

        return User::query()
            ->where(function ($query) use ($identifiers, $login, $normalized) {
                foreach ($identifiers as $column) {
                    $query->orWhere($column, in_array($column, ['dni', 'cuit'], true) ? $normalized : $login);
                }
            })
            ->with('role')
            ->first();
    }
}
