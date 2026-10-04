<?php

namespace Tests\Feature\Core;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use RuntimeException;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function enableGoogle(): void
    {
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
    }

    private function fakeGoogleUser(string $email, bool $verified = true): void
    {
        $google = (new GoogleUser)->setRaw(['email' => $email, 'email_verified' => $verified])->map(['email' => $email, 'name' => 'Prueba']);
        Socialite::shouldReceive('driver->user')->andReturn($google);
    }

    public function test_button_only_shows_when_google_is_configured(): void
    {
        User::factory()->role('admin')->create();

        $this->get('/login')->assertOk()->assertDontSee('Ingresar con Google');

        $this->enableGoogle();
        $this->get('/login')->assertOk()->assertSee('Ingresar con Google');
        $this->get('/recuperar-contrasena')->assertOk()->assertSee('Ingresar con Google');
    }

    public function test_login_shows_specific_field_label_and_no_technical_owner_link(): void
    {
        User::factory()->role('admin')->create();

        $this->get('/login')->assertOk()
            ->assertSee('Usuario, DNI, CUIT, código interno o email')
            ->assertDontSee('¿Sos el dueño');
    }

    public function test_existing_user_logs_in_with_google_by_email(): void
    {
        $this->enableGoogle();
        $user = User::factory()->role('admin')->create(['email' => 'santi@gmail.com']);
        $this->fakeGoogleUser('Santi@Gmail.com');

        $this->get('/login/google/callback')->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login', 'description' => 'Inicio de sesión con Google']);
    }

    public function test_unknown_email_is_rejected_and_audited(): void
    {
        $this->enableGoogle();
        User::factory()->role('admin')->create();
        $this->fakeGoogleUser('desconocido@gmail.com');

        $this->get('/login/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_failed']);
    }

    public function test_inactive_user_cannot_login_with_google(): void
    {
        $this->enableGoogle();
        User::factory()->role('admin')->inactive()->create(['email' => 'baja@gmail.com']);
        $this->fakeGoogleUser('baja@gmail.com');

        $this->get('/login/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->enableGoogle();
        User::factory()->role('admin')->create(['email' => 'santi@gmail.com']);
        $this->fakeGoogleUser('santi@gmail.com', verified: false);

        $this->get('/login/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_google_error_or_cancel_returns_to_login(): void
    {
        $this->enableGoogle();
        User::factory()->role('admin')->create();
        Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('invalid state'));

        $this->get('/login/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_google_routes_are_disabled_without_credentials(): void
    {
        User::factory()->role('admin')->create();
        $this->get('/login/google')->assertRedirect(route('login'))->assertSessionHas('error');
        $this->get('/login/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');
    }
}
