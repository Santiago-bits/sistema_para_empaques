<?php

namespace Tests\Feature\Core;

use App\Models\Backup;
use App\Models\Producer;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_generate_download_and_verify_backup(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('backups.index'))->assertOk()->assertSee('No hay ningún backup exitoso');

        $this->post(route('backups.store'))->assertRedirect()->assertSessionHas('success');
        $backup = Backup::query()->sole();
        $this->assertSame('success', $backup->status);
        $this->assertNotNull($backup->verified_at);
        $this->assertSame(64, strlen($backup->checksum));
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup']);

        $this->get(route('backups.download', $backup))->assertOk()->assertDownload($backup->filename);
        $this->post(route('backups.verify', $backup))->assertSessionHas('success');
    }

    public function test_tampered_backup_fails_verification(): void
    {
        $this->actingAsRole('admin');
        $backup = app(BackupService::class)->run('manual');
        Storage::disk('local')->put('backups/'.$backup->filename, 'archivo alterado');

        $this->post(route('backups.verify', $backup))->assertSessionHas('error');
        $this->assertNull($backup->fresh()->verified_at);
    }

    public function test_restore_requires_permission_word_and_password(): void
    {
        $this->actingAsRole('admin'); // admin NO tiene backups.restore
        $backup = app(BackupService::class)->run('manual');
        $this->post(route('backups.restore', $backup), ['confirmation' => 'RESTAURAR', 'password' => 'password', 'reason' => 'Prueba de restauración'])
            ->assertForbidden();

        $this->actingAsRole('super_admin');
        $this->post(route('backups.restore', $backup), ['confirmation' => 'restaurar', 'password' => 'password', 'reason' => 'Prueba de restauración'])
            ->assertSessionHasErrors('confirmation');
        $this->post(route('backups.restore', $backup), ['confirmation' => 'RESTAURAR', 'password' => 'incorrecta', 'reason' => 'Prueba de restauración'])
            ->assertSessionHasErrors('password');
        $this->assertSame(1, Backup::query()->count(), 'Sin confirmación válida no se toca nada.');
    }

    public function test_operators_cannot_access_backups(): void
    {
        $this->actingAsRole('loads_operator');
        $this->get(route('backups.index'))->assertForbidden();
        $this->post(route('backups.store'))->assertForbidden();
    }
}
