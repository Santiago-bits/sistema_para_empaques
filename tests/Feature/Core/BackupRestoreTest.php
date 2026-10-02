<?php

namespace Tests\Feature\Core;

use App\Models\Backup;
use App\Models\Producer;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Restauración completa. Usa DatabaseMigrations (sin transacción envolvente) porque la
 * restauración ejecuta su propio BEGIN/COMMIT sobre la base.
 */
class BackupRestoreTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(\Database\Seeders\SystemSeeder::class);
    }

    public function test_restore_brings_back_previous_data_and_keeps_pre_restore_backup(): void
    {
        $user = $this->actingAsRole('super_admin');
        Producer::query()->create(['code' => 'P1', 'name' => 'Antes del backup']);
        $backup = app(BackupService::class)->run('manual', $user);
        Producer::query()->create(['code' => 'P2', 'name' => 'Después del backup']);

        $this->post(route('backups.restore', $backup), ['confirmation' => 'RESTAURAR', 'password' => 'password', 'reason' => 'Prueba de restauración'])
            ->assertRedirect(route('backups.index'));

        $this->assertDatabaseHas('producers', ['code' => 'P1']);
        $this->assertDatabaseMissing('producers', ['code' => 'P2']);
        $this->assertTrue(Backup::query()->where('type', 'restore')->where('status', 'success')->exists(), 'Queda el backup previo a la restauración.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'restore_backup', 'reason' => 'Prueba de restauración']);
        $this->assertFalse(app()->isDownForMaintenance());
    }

}
