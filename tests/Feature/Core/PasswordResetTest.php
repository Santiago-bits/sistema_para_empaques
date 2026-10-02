<?php

namespace Tests\Feature\Core;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use App\Notifications\ResetPasswordLink;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_links_to_password_recovery(): void
    {
        User::factory()->role('admin')->create();
        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/recuperar-contrasena')->assertOk()->assertSee('Recuperar contraseña');
    }

    public function test_user_with_email_receives_reset_link(): void
    {
        Notification::fake();
        $admin = User::factory()->role('admin')->create();
        $user = User::factory()->role('operator')->create(['username' => 'ana', 'email' => 'ana@galpon.test']);

        $this->post('/recuperar-contrasena', ['login' => 'ana'])
            ->assertRedirect(route('login'))->assertSessionHas('status', PasswordResetController::GENERIC_STATUS);

        Notification::assertSentTo($user, ResetPasswordLink::class);
        Notification::assertNothingSentTo($admin);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset_requested', 'auditable_id' => $user->id]);
    }

    public function test_user_without_email_notifies_admins_once(): void
    {
        Notification::fake();
        $admin = User::factory()->role('admin')->create();
        $operator = User::factory()->role('operator')->create(['username' => 'juan', 'email' => null]);

        $this->post('/recuperar-contrasena', ['login' => 'juan'])->assertSessionHas('status', PasswordResetController::GENERIC_STATUS);
        $this->post('/recuperar-contrasena', ['login' => 'juan']);

        Notification::assertSentToTimes($admin, PasswordResetRequested::class, 1);
        Notification::assertNotSentTo($operator, PasswordResetRequested::class);
    }

    public function test_response_does_not_reveal_if_user_exists(): void
    {
        Notification::fake();
        User::factory()->role('admin')->create();
        User::factory()->role('operator')->inactive()->create(['username' => 'baja', 'email' => 'baja@galpon.test']);

        foreach (['noexiste', 'baja'] as $login) {
            $this->post('/recuperar-contrasena', ['login' => $login])
                ->assertRedirect(route('login'))->assertSessionHas('status', PasswordResetController::GENERIC_STATUS);
        }
        Notification::assertNothingSent();
    }

    public function test_recovery_requests_are_rate_limited(): void
    {
        Notification::fake();
        User::factory()->role('admin')->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/recuperar-contrasena', ['login' => 'alguien']);
        }
        $this->post('/recuperar-contrasena', ['login' => 'alguien'])->assertStatus(429);
    }

    public function test_reset_link_sets_new_password_and_closes_sessions(): void
    {
        $user = User::factory()->role('operator')->create(['email' => 'ana@galpon.test']);
        $user->createToken('lector');
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee('Crear contraseña nueva');

        $this->post('/restablecer-contrasena', [
            'token' => $token, 'email' => $user->email, 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaClave2026', $user->password));
        $this->assertNotNull($user->password_changed_at);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset', 'auditable_id' => $user->id]);
        $this->assertDatabaseMissing('audit_logs', ['new_values' => 'NuevaClave2026']);

        // El enlace es de un solo uso.
        $this->post('/restablecer-contrasena', [
            'token' => $token, 'email' => $user->email, 'password' => 'OtraClave2026', 'password_confirmation' => 'OtraClave2026',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('NuevaClave2026', $user->fresh()->password));
    }

    public function test_invalid_token_and_weak_password_are_rejected(): void
    {
        $user = User::factory()->role('operator')->create(['email' => 'ana@galpon.test']);
        $token = Password::broker()->createToken($user);

        $this->post('/restablecer-contrasena', ['token' => 'inventado', 'email' => $user->email, 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])
            ->assertSessionHasErrors('email');
        $this->post('/restablecer-contrasena', ['token' => $token, 'email' => $user->email, 'password' => '123', 'password_confirmation' => '123'])
            ->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_admin_assigns_temporary_password_and_user_must_change_it(): void
    {
        $admin = $this->actingAsRole('admin');
        $user = User::factory()->role('operator')->create(['username' => 'juan']);

        $response = $this->post(route('admin.users.password.reset', $user))->assertRedirect(route('admin.users.show', $user));
        $temporary = $response->getSession()->get('temporary_password');
        $this->assertMatchesRegularExpression('/^[A-Za-z2-9]{4}-[A-Za-z2-9]{4}-[A-Za-z2-9]{2}$/', $temporary);
        $this->get(route('admin.users.show', $user))->assertSee($temporary);
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset_admin', 'auditable_id' => $user->id, 'user_id' => $admin->id]);

        // El usuario entra con la temporal y queda encerrado en la pantalla de cambio.
        $this->post('/logout');
        $this->post('/login', ['login' => 'juan', 'password' => $temporary]);
        $this->get('/dashboard')->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk()->assertSee('contraseña temporal');

        $this->put(route('password.change.update'), ['current_password' => $temporary, 'password' => $temporary, 'password_confirmation' => $temporary])
            ->assertSessionHas('error');
        $this->put(route('password.change.update'), ['current_password' => 'mala', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('password.change.update'), ['current_password' => $temporary, 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])
            ->assertRedirect(route('home'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NuevaClave2026', $user->password));
        $this->get(route('password.change'))->assertRedirect(route('home'));
    }

    public function test_kiosk_user_with_temporary_password_can_change_it(): void
    {
        $user = User::factory()->role('operator')->create(['kiosk_mode' => true, 'must_change_password' => true]);
        $this->actingAs($user);

        $this->get(route('kiosk'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk();
    }

    public function test_temporary_password_cannot_be_assigned_without_permission_or_to_super_admin(): void
    {
        $operator = User::factory()->role('operator')->create();
        $superAdmin = User::factory()->role('super_admin')->create();

        $this->actingAsRole('operator');
        $this->post(route('admin.users.password.reset', $operator))->assertForbidden();

        $this->actingAsRole('admin');
        $this->post(route('admin.users.password.reset', $superAdmin))->assertForbidden();
        $this->assertFalse($superAdmin->fresh()->must_change_password);
    }

    public function test_admin_cannot_reset_own_password_from_user_panel(): void
    {
        $admin = $this->actingAsRole('admin');
        $this->post(route('admin.users.password.reset', $admin))->assertSessionHas('error');
        $this->assertFalse($admin->fresh()->must_change_password);
    }

    public function test_generated_temporary_passwords_are_strong_and_unique(): void
    {
        $passwords = collect(range(1, 50))->map(fn () => PasswordResetService::generateTemporary());
        $this->assertCount(50, $passwords->unique());
        $passwords->each(fn ($p) => $this->assertMatchesRegularExpression('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', str_replace('-', '', $p)));
    }
}
