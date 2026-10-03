<?php

namespace Tests\Feature\Core;

use App\Models\Producer;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Backup y restauración de MySQL sin mysqldump (hosting sin proc_open). Sólo corre contra MySQL/MariaDB:
 *   DB_CONNECTION=mysql DB_DATABASE=galpon_verify php artisan test --filter=BackupPhpDumpTest
 */
class BackupPhpDumpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Requiere MySQL/MariaDB.');
        }
        config(['galpon.backup_driver' => 'php']);
    }

    public function test_php_dump_is_valid_and_restores_the_data(): void
    {
        $user = $this->actingAsRole('super_admin');
        Producer::query()->create(['code' => 'P-1', 'name' => "Finca \"La Esperanza\"; con 'comillas'\ny salto de línea"]);

        $service = app(BackupService::class);
        $this->assertFalse($service->useNativeTools('mysqldump'));
        $backup = $service->run('manual', $user);
        $this->assertSame('success', $backup->status, (string) $backup->error);
        $this->assertTrue($service->verify($backup)['ok']);

        Producer::query()->create(['code' => 'P-2', 'name' => 'Cargado después del backup']);
        Producer::query()->where('code', 'P-1')->update(['name' => 'cambiado']);

        $service->restore($backup->fresh(), $user, 'Prueba de restauración en PHP');

        $this->assertFalse(Producer::query()->where('code', 'P-2')->exists());
        $this->assertSame("Finca \"La Esperanza\"; con 'comillas'\ny salto de línea", Producer::query()->where('code', 'P-1')->value('name'));
    }
}
