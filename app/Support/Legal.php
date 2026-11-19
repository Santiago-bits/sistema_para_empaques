<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/**
 * Términos y condiciones y política de privacidad del servicio.
 *
 * Los textos están en resources/views/legal. Si se cambia algo importante del texto, subir VERSION: el
 * administrador de cada galpón los vuelve a aceptar en su próximo ingreso (queda registrado quién, cuándo y
 * desde qué IP). Los datos del titular (nombre, CUIT, domicilio, jurisdicción) se cargan en
 * Administración general → Términos y condiciones.
 */
class Legal
{
    public const VERSION = '2026-11-19';

    /** Datos del titular del servicio para mostrar en los textos. */
    public static function provider(): array
    {
        $email = trim((string) setting('legal.email', '')) ?: trim((string) setting('support.email', ''));

        return [
            'name' => trim((string) setting('legal.provider_name', '')) ?: 'Base Ocho',
            'cuit' => trim((string) setting('legal.provider_cuit', '')),
            'address' => trim((string) setting('legal.provider_address', '')),
            'email' => $email,
            'jurisdiction' => trim((string) setting('legal.jurisdiction', '')),
            'url' => rtrim((string) config('app.url'), '/'),
        ];
    }

    /**
     * Quién tiene que aceptarlos: el administrador de cada galpón (el que contrata en nombre del galpón).
     * El dueño del sistema no; el resto de la gente queda avisada en la pantalla de ingreso.
     */
    public static function mustAccept(?User $user): bool
    {
        if (! $user || $user->isSuperAdmin() || $user->warehouse_id === null) {
            return false;
        }
        if ($user->terms_version === self::VERSION) {
            return false;
        }

        return $user->role?->slug === Role::ADMIN || $user->can('settings.manage');
    }
}
