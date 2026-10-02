<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Ataques desde otra PC de la LAN: IP falsificada y encabezado Host envenenado. */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_spoofed_x_forwarded_for_does_not_bypass_login_rate_limit(): void
    {
        User::factory()->role('admin')->create(['username' => 'carlos']);
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['X-Forwarded-For' => '10.0.0.'.$i])->post('/login', ['login' => 'carlos', 'password' => 'mala']);
        }
        $this->withHeaders(['X-Forwarded-For' => '10.0.0.99'])->post('/login', ['login' => 'carlos', 'password' => 'mala'])->assertStatus(429);
    }

    public function test_spoofed_ip_is_not_recorded_in_audit(): void
    {
        User::factory()->role('admin')->create(['username' => 'carlos']);
        $this->withHeaders(['X-Forwarded-For' => '8.8.8.8'])->post('/login', ['login' => 'carlos', 'password' => 'mala']);
        $this->assertDatabaseMissing('audit_logs', ['ip_address' => '8.8.8.8']);
    }

    public function test_admin_cannot_grant_super_admin_only_permissions(): void
    {
        $admin = $this->actingAsRole('admin');
        $other = User::factory()->role('supervisor')->create();
        $adminRole = $admin->role;

        // A sí mismo: no puede gestionar sus permisos individuales.
        $this->put(route('admin.users.permissions.update', $admin), ['overrides' => ['backups.restore' => 'grant']])->assertForbidden();
        // A otro usuario: el permiso protegido se ignora.
        $this->put(route('admin.users.permissions.update', $other), ['overrides' => ['backups.restore' => 'grant', 'costs.view' => 'grant']]);
        $other->refresh()->flushPermissionCache();
        $this->assertFalse($other->hasPermission('backups.restore'));
        $this->assertTrue($other->hasPermission('costs.view'));
        // A su propio rol: tampoco.
        $this->put(route('admin.roles.update', $adminRole), ['name' => $adminRole->name, 'permissions' => ['backups.restore', 'dashboard.view']]);
        $this->assertFalse($adminRole->fresh()->permissions()->where('slug', 'backups.restore')->exists());
    }

    public function test_changing_password_closes_other_sessions_and_tokens(): void
    {
        $user = $this->actingAsRole('admin');
        $user->createToken('balanza', ['read']);

        $this->put(route('profile.password'), ['current_password' => 'password', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])
            ->assertSessionHas('success');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_csv_export_neutralizes_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\")", \App\Services\ExportService::neutralizeFormula('=HYPERLINK("http://x")'));
        $this->assertSame("'@SUM(A1)", \App\Services\ExportService::neutralizeFormula('@SUM(A1)'));
        $this->assertSame('-12.5', \App\Services\ExportService::neutralizeFormula('-12.5'));
        $this->assertSame('Finca Norte', \App\Services\ExportService::neutralizeFormula('Finca Norte'));
        $this->assertSame(18.5, \App\Services\ExportService::neutralizeFormula(18.5));
    }

    public function test_reset_email_link_ignores_poisoned_host_header(): void
    {
        Notification::fake();
        User::factory()->role('admin')->create();
        $user = User::factory()->role('operator')->create(['username' => 'ana', 'email' => 'ana@galpon.test']);

        $this->withHeaders(['Host' => 'pc-atacante', 'X-Forwarded-Host' => 'pc-atacante'])
            ->post('http://pc-atacante/recuperar-contrasena', ['login' => 'ana']);

        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_starts_with($url, rtrim(config('app.url'), '/').'/') && ! str_contains($url, 'pc-atacante');
        });
    }
}
