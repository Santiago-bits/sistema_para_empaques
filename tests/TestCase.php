<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\SystemSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Ejecuta SystemSeeder (roles, permisos, módulos, galpón) en tests con RefreshDatabase. */
    protected bool $seedSystem = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        if ($this->seedSystem && in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(SystemSeeder::class);
        }
    }

    /** Crea un usuario con el rol indicado y lo autentica. */
    protected function actingAsRole(string $role = 'admin', array $attributes = []): User
    {
        $user = User::factory()->role($role)->create($attributes);
        $this->actingAs($user);

        return $user;
    }

    /** Crea un usuario sin rol con los permisos indicados (concesiones individuales). */
    protected function actingWithPermissions(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $ids = \App\Models\Permission::query()->whereIn('slug', $permissions)->pluck('id');
        $user->permissionOverrides()->attach($ids->mapWithKeys(fn ($id) => [$id => ['granted' => true]])->all());
        $this->actingAs($user);

        return $user;
    }
}
