<?php

namespace Tests\Feature\Core;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Términos y condiciones, política de privacidad y su aceptación por el administrador de cada galpón. */
class LegalTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_and_privacy_are_public_and_show_the_provider(): void
    {
        User::factory()->role('admin')->create();
        app(SettingsService::class)->set('legal.provider_name', 'Base Ocho Software');
        app(SettingsService::class)->set('legal.provider_cuit', '20-12345678-6');
        app(SettingsService::class)->set('legal.jurisdiction', 'los tribunales ordinarios de la Ciudad de Mendoza');

        $this->get(route('legal.terms'))->assertOk()->assertSee('Términos y condiciones de uso')->assertSee('Base Ocho Software')
            ->assertSee('20-12345678-6')->assertSee('los tribunales ordinarios de la Ciudad de Mendoza')->assertSee('ARCA');
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Ley 25.326')->assertSee('AGENCIA DE ACCESO A LA INFORMACIÓN PÚBLICA');
        $this->get(route('login'))->assertOk()->assertSee(route('legal.terms'))->assertSee(route('legal.privacy'));
    }

    public function test_the_warehouse_admin_accepts_once_and_it_is_recorded(): void
    {
        $admin = $this->actingAsRole('admin', ['terms_version' => null]);

        // No puede usar el sistema hasta aceptar (ni por la web ni por pedidos JSON).
        $this->get(route('dashboard'))->assertRedirect(route('legal.accept'));
        $this->getJson(route('dashboard'))->assertForbidden();
        $this->get(route('legal.accept'))->assertOk()->assertSee('Antes de seguir');
        // Las páginas de los textos y salir siguen abiertas.
        $this->get(route('legal.terms'))->assertOk();

        $this->post(route('legal.accept.store'), [])->assertSessionHasErrors('accept');
        $this->assertNull($admin->fresh()->terms_accepted_at);

        $this->post(route('legal.accept.store'), ['accept' => '1'])->assertRedirect(route('dashboard'));
        $admin->refresh();
        $this->assertSame(Legal::VERSION, $admin->terms_version);
        $this->assertNotNull($admin->terms_accepted_at);
        $this->assertTrue(AuditLog::query()->where('action', 'terms_accepted')->where('auditable_id', $admin->id)->exists());

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('legal.accept'))->assertRedirect(route('home'));

        // Si cambia la versión de los términos, los vuelve a aceptar.
        $admin->forceFill(['terms_version' => '2020-01-01'])->save();
        $this->get(route('dashboard'))->assertRedirect(route('legal.accept'));
    }

    public function test_operators_and_the_owner_are_not_stopped(): void
    {
        $this->actingAsRole('quality', ['terms_version' => null]);
        $this->get(route('dashboard'))->assertOk();

        $this->actingAsRole(Role::SUPER_ADMIN, ['terms_version' => null]);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_only_the_owner_edits_the_provider_data(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('superadmin.legal'))->assertNotFound();
        $this->put(route('superadmin.legal.update'), ['provider_name' => 'Otro'])->assertNotFound();

        $this->actingAsRole(Role::SUPER_ADMIN);
        $this->post(route('superadmin.confirm.store'), ['password' => 'password']);
        $this->get(route('superadmin.legal'))->assertOk()->assertSee('Titular del servicio')->assertSee('Aceptación en cada galpón');
        $this->put(route('superadmin.legal.update'), ['provider_name' => 'Base Ocho', 'provider_cuit' => '20-11111111-1'])->assertSessionHasErrors('provider_cuit');
        $this->put(route('superadmin.legal.update'), ['provider_name' => 'Base Ocho SRL', 'email' => 'legal@baseocho.online', 'provider_address' => 'Calle 123'])
            ->assertRedirect(route('superadmin.legal'));
        $this->assertSame('Base Ocho SRL', setting('legal.provider_name'));
        $this->get(route('legal.terms'))->assertSee('Base Ocho SRL')->assertSee('legal@baseocho.online')->assertSee('Calle 123');
    }
}
