<?php

namespace Tests\Feature\Core;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        User::factory()->role('admin')->create();
        $this->get('/login')->assertOk()->assertSee('Ingresar al sistema');
    }

    public function test_user_can_login_with_username_or_dni(): void
    {
        $user = User::factory()->role('admin')->create(['username' => 'carlos', 'dni' => '30123456']);

        $this->post('/login', ['login' => 'carlos', 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->post('/login', ['login' => '30.123.456', 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected_and_audited(): void
    {
        User::factory()->role('admin')->create(['username' => 'carlos']);
        $this->post('/login', ['login' => 'carlos', 'password' => 'mala'])->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_failed']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->role('admin')->inactive()->create(['username' => 'baja']);
        $this->post('/login', ['login' => 'baja', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->role('admin')->create(['username' => 'carlos']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'carlos', 'password' => 'mala']);
        }
        $this->post('/login', ['login' => 'carlos', 'password' => 'password'])->assertStatus(429);
    }

    public function test_dashboard_requires_authentication(): void
    {
        User::factory()->role('admin')->create();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_sees_dashboard_and_admin_pages(): void
    {
        $this->actingAsRole('admin');
        foreach (['/dashboard', '/admin/usuarios', '/admin/roles', '/admin/modulos', '/admin/configuracion', '/admin/auditoria', '/admin/sesiones', '/perfil'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (array_keys(\App\Http\Controllers\Admin\SettingController::GROUPS) as $tab) {
            $this->get('/admin/configuracion?tab='.$tab)->assertOk();
        }
    }

    public function test_redirects_to_installer_when_no_users(): void
    {
        $this->get('/login')->assertRedirect(route('install.show'));
        $this->get('/instalar')->assertOk()->assertSee('Configuración inicial');
    }
}
