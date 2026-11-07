<?php

namespace Tests\Feature\Core;

use App\Models\SystemError;
use App\Notifications\ErrorDigestMail;
use App\Notifications\TestMail;
use App\Services\ErrorMonitorService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Avisos de errores por correo al desarrollador y limpieza semanal de errores y logs. */
class ErrorMonitorTest extends TestCase
{
    use RefreshDatabase;

    private string $log;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp']);
        app(SettingsService::class)->set('support.email', 'dev@example.com');
        @unlink(storage_path(ErrorMonitorService::STATE_FILE));
        // Carpeta de logs propia: la limpieza no toca los logs reales.
        $dir = storage_path('framework/testing/monitor-logs');
        File::ensureDirectoryExists($dir);
        config(['galpon.logs_path' => $dir]);
        $this->log = $dir.'/laravel.log';
        File::put($this->log, '');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/monitor-logs'));
        @unlink(storage_path(ErrorMonitorService::STATE_FILE));
        parent::tearDown();
    }

    private function error(string $message, string $file = '/app/Services/X.php', int $line = 10): SystemError
    {
        return SystemError::query()->create(['code' => 'ERR-'.uniqid(), 'category' => 'exception', 'message' => $message,
            'exception' => 'RuntimeException', 'file' => $file, 'line' => $line, 'url' => 'http://x/cargas', 'created_at' => now()]);
    }

    public function test_new_errors_and_log_lines_are_mailed_grouped_and_only_once(): void
    {
        $monitor = app(ErrorMonitorService::class);
        $this->error('Error viejo');
        File::append($this->log, "[2026-11-01 10:00:00] production.ERROR: Línea vieja del log\n");
        $this->assertSame(0, $monitor->check()['found']); // La primera vez empieza desde ahora.

        $this->error('División por cero');
        $this->error('División por cero');
        $this->error('Tabla inexistente', '/app/Y.php', 5);
        File::append($this->log, "[2026-11-07 10:00:00] production.ERROR: La cola falló al procesar el trabajo 123 {\"exception\":\"x\"}\n#0 traza\n");
        File::append($this->log, "[2026-11-07 10:01:00] production.INFO: Todo bien\n");
        File::append($this->log, "[2026-11-07 10:02:00] production.CRITICAL: Sin conexión a la base\n");

        $r = $monitor->check();
        $this->assertTrue($r['sent']);
        $this->assertSame(5, $r['found']);
        $this->assertSame(4, $r['groups']);
        Notification::assertSentOnDemand(ErrorDigestMail::class, function (ErrorDigestMail $mail, array $channels, AnonymousNotifiable $n) {
            $html = (string) $mail->toMail($n)->render();

            return $n->routes['mail'] === 'dev@example.com' && $mail->total === 5
                && str_contains($html, 'División por cero') && str_contains($html, '2 veces')
                && str_contains($html, 'La cola falló') && str_contains($html, 'Sin conexión a la base')
                && ! str_contains($html, 'Todo bien') && ! str_contains($html, 'Línea vieja');
        });

        // No se repite lo ya avisado.
        $this->assertSame(0, $monitor->check()['found']);
        Notification::assertSentOnDemandTimes(ErrorDigestMail::class, 1);
    }

    public function test_cooldown_keeps_errors_pending_until_the_next_mail(): void
    {
        $monitor = app(ErrorMonitorService::class);
        $monitor->check();
        $this->error('Primero');
        $this->assertTrue($monitor->check()['sent']);

        $this->error('Segundo');
        $r = $monitor->check();
        $this->assertFalse($r['sent']);
        $this->assertStringContainsString('15 minutos', $r['reason']);
        $this->assertSame(1, $monitor->status()['pending']);

        $this->travel(16)->minutes();
        $this->error('Tercero');
        $r = $monitor->check();
        $this->assertTrue($r['sent']);
        $this->assertSame(2, $r['found']); // Segundo (pendiente) + Tercero.
        Notification::assertSentOnDemandTimes(ErrorDigestMail::class, 2);
    }

    public function test_without_mail_configured_or_disabled_nothing_is_sent_and_the_reason_is_clear(): void
    {
        $monitor = app(ErrorMonitorService::class);
        $monitor->check();
        $this->error('Falla');
        config(['mail.default' => 'log']);
        $this->assertStringContainsString('MAIL_MAILER', $monitor->check()['reason']);

        config(['mail.default' => 'smtp']);
        app(SettingsService::class)->set('support.error_alerts', false);
        $this->assertStringContainsString('apagados', $monitor->check()['reason']);
        Notification::assertNothingSent();
    }

    public function test_weekly_cleanup_deletes_old_errors_and_empties_logs_but_keeps_recent_errors(): void
    {
        $old = $this->error('Viejo');
        $old->forceFill(['created_at' => now()->subDays(8)])->save();
        $recent = $this->error('Reciente');
        File::put($this->log, str_repeat("[2026-11-07 10:00:00] production.INFO: x\n", 50));
        $stale = dirname($this->log).'/laravel-viejo.log';
        File::put($stale, 'viejo');
        touch($stale, now()->subDays(9)->getTimestamp());

        Artisan::call('galpon:cleanup-logs');

        $this->assertNull(SystemError::query()->find($old->id));
        $this->assertNotNull(SystemError::query()->find($recent->id));
        $this->assertSame('', File::get($this->log));
        $this->assertFileDoesNotExist($stale);
        $this->assertNotNull(app(ErrorMonitorService::class)->status()['last_reset_at']);
    }

    public function test_tasks_are_scheduled_without_opening_processes(): void
    {
        Artisan::call('schedule:list');
        $out = Artisan::output();
        $this->assertStringContainsString('galpon:errors-watch', $out);
        $this->assertStringContainsString('galpon:cleanup-logs', $out);
        $this->assertStringNotContainsString("Schedule::command('galpon:", (string) file_get_contents(base_path('routes/console.php')));
    }

    public function test_developer_panel_manages_alerts_and_only_the_developer_can(): void
    {
        $this->actingAsRole('super_admin');
        $this->get(route('developer.index'))->assertOk()->assertSee('Avisos por correo')->assertSee('dev@example.com');

        $this->put(route('developer.alerts.update'), ['email' => 'otro@example.com', 'enabled' => '1'])->assertSessionHas('success');
        $this->assertSame('otro@example.com', setting('support.email'));
        $this->put(route('developer.alerts.update'), ['email' => 'no-es-un-correo'])->assertSessionHasErrors('email');

        $this->post(route('developer.alerts.test'))->assertSessionHas('success');
        Notification::assertSentOnDemand(TestMail::class, fn ($m, $c, $n) => $n->routes['mail'] === 'otro@example.com');

        config(['mail.default' => 'log']);
        $this->post(route('developer.alerts.test'))->assertSessionHas('error');
        $this->get(route('developer.index'))->assertSee('El servidor todavía no puede mandar correos');

        $this->post(route('developer.alerts.check'))->assertSessionHas('success');
        $this->post(route('developer.alerts.cleanup'))->assertSessionHas('success');

        // El administrador del galpón no entra.
        $this->actingAsRole('admin');
        $this->get(route('developer.index'))->assertForbidden();
        $this->post(route('developer.alerts.test'))->assertForbidden();
        $this->put(route('developer.alerts.update'), ['email' => 'hacker@example.com'])->assertForbidden();
        $this->assertSame('otro@example.com', setting('support.email'));
    }
}
