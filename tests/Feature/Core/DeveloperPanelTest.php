<?php

namespace Tests\Feature\Core;

use App\Models\License;
use App\Models\SystemError;
use App\Support\LogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_all_sections(): void
    {
        $this->actingAsRole('super_admin');
        $error = SystemError::query()->create(['code' => 'ERR-20261001-ABCDE', 'category' => 'exception', 'message' => 'Falla de prueba', 'trace' => "#0 password=secreto123\n#1 x", 'created_at' => now()]);

        $this->get(route('developer.index'))->assertOk()->assertSee('Servidor')->assertSee('ERR-20261001-ABCDE');
        $this->get(route('developer.errors', ['q' => 'ABCDE']))->assertOk()->assertSee('Falla de prueba');
        $this->get(route('developer.errors.show', $error))->assertOk()->assertSee('password=********')->assertDontSee('secreto123');
        $this->get(route('developer.logs'))->assertOk();
        $this->get(route('developer.licenses.index'))->assertOk();
    }

    public function test_admin_and_operators_cannot_enter(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('developer.index'))->assertForbidden();
        $this->get(route('developer.logs'))->assertForbidden();
        $this->get(route('developer.licenses.index'))->assertForbidden();
    }

    public function test_license_crud_and_expired_notice_never_blocks(): void
    {
        $this->actingAsRole('super_admin');
        $this->post(route('developer.licenses.store'), [
            'installation_id' => config('galpon.installation_id'), 'client_name' => 'Empaque Demo SA', 'plan' => 'pro', 'status' => 'active',
            'starts_on' => '2025-01-01', 'expires_on' => '2025-12-31', 'modules' => ['loads', 'billing'],
        ])->assertRedirect(route('developer.licenses.index'));

        $license = License::query()->sole();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}(-[A-Z0-9]{6}){3}$/', $license->license_key);
        $this->assertSame('expired', $license->effectiveStatus());

        // Vencida: se ve el aviso pero el sistema sigue funcionando.
        $this->get(route('dashboard'))->assertOk()->assertSee('Licencia vencida');

        $this->put(route('developer.licenses.update', $license), [
            'installation_id' => $license->installation_id, 'client_name' => 'Empaque Demo SA', 'plan' => 'pro', 'status' => 'active',
            'starts_on' => '2025-01-01', 'expires_on' => '', 'modules' => ['loads'],
        ])->assertRedirect();
        $this->assertSame('active', $license->fresh()->effectiveStatus());
        $this->get(route('dashboard'))->assertDontSee('Licencia vencida');

        $this->post(route('developer.licenses.store'), ['installation_id' => '../etc', 'client_name' => 'x', 'plan' => 'hack', 'status' => 'active', 'starts_on' => 'no'])
            ->assertSessionHasErrors(['installation_id', 'plan', 'starts_on']);
    }

    public function test_log_reader_masks_secrets_and_rejects_paths(): void
    {
        $this->assertStringNotContainsString('abc123', LogReader::mask('Authorization: Bearer abc123'));
        $this->assertStringNotContainsString('clave', LogReader::mask('mysql://root:clave@127.0.0.1/galpon'));
        $this->assertNotSame(storage_path('logs/../../.env'), LogReader::resolve('../../.env'));
    }
}
