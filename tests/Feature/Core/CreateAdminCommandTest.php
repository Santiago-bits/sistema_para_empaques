<?php

namespace Tests\Feature\Core;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_super_admin_from_console(): void
    {
        $this->artisan('galpon:create-admin')
            ->expectsQuestion('Nombre', 'Soporte')
            ->expectsQuestion('Apellido', 'Técnico')
            ->expectsQuestion('Usuario (para ingresar)', 'soporte')
            ->expectsQuestion('Email (opcional)', '')
            ->expectsQuestion('Contraseña (no se muestra al escribir)', 'ClaveSegura2026')
            ->expectsQuestion('Repetí la contraseña', 'ClaveSegura2026')
            ->expectsConfirmation('¿Crear el Super Administrador "soporte"?', 'yes')
            ->assertSuccessful();

        $user = User::query()->where('username', 'soporte')->sole();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue(Hash::check('ClaveSegura2026', $user->password));
    }

    public function test_rejects_weak_password(): void
    {
        $this->artisan('galpon:create-admin')
            ->expectsQuestion('Nombre', 'A')->expectsQuestion('Apellido', 'B')
            ->expectsQuestion('Usuario (para ingresar)', 'debil')->expectsQuestion('Email (opcional)', '')
            ->expectsQuestion('Contraseña (no se muestra al escribir)', '123')->expectsQuestion('Repetí la contraseña', '123')
            ->expectsQuestion('Contraseña (no se muestra al escribir)', '123')->expectsQuestion('Repetí la contraseña', '123')
            ->expectsQuestion('Contraseña (no se muestra al escribir)', '123')->expectsQuestion('Repetí la contraseña', '123')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['username' => 'debil']);
    }
}
