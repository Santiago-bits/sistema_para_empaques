<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens de API (Sanctum) para balanzas, sensores e integraciones. El token en claro
 * se muestra UNA sola vez al crearlo; en la base sólo queda su hash SHA-256 y la
 * auditoría nunca guarda el valor.
 */
class ApiTokenService
{
    /** Habilidades disponibles para los tokens. */
    public const ABILITIES = [
        'read' => 'Lectura (cajones, pallets, cargas, insumos, reportes)',
        'scale:write' => 'Balanza: enviar lecturas de peso',
        'sensors:write' => 'Sensores: enviar lecturas de temperatura/humedad',
    ];

    public function __construct(private readonly AuditService $audit)
    {
    }

    /** @param list<string> $abilities */
    public function create(User $owner, string $name, array $abilities, User $actor, ?\DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $abilities = array_values(array_intersect(array_keys(self::ABILITIES), $abilities));
        if ($abilities === []) {
            throw new BusinessException('Elegí al menos un permiso para el token.');
        }
        if (! $actor->isSuperAdmin() && ! $owner->is($actor)) {
            throw new BusinessException('Sólo podés crear tokens a tu nombre.');
        }

        $token = $owner->createToken($name, $abilities, $expiresAt);

        $this->audit->log('token', $owner, null, [
            'id' => $token->accessToken->id,
            'name' => $name,
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->format('Y-m-d H:i'),
        ], 'Creó el token de API "'.$name.'"');

        return $token;
    }

    public function revoke(PersonalAccessToken $token): void
    {
        $owner = $token->tokenable;
        $data = ['id' => $token->id, 'name' => $token->name, 'abilities' => $token->abilities];
        $token->delete();

        $this->audit->log('token', $owner instanceof User ? $owner : null, $data, null, 'Revocó el token de API "'.$data['name'].'"');
    }
}
