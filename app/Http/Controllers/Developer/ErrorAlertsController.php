<?php

namespace App\Http\Controllers\Developer;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Notifications\TestMail;
use App\Services\AuditService;
use App\Services\ErrorMonitorService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/** Avisos de errores por correo: destino, encendido, prueba de correo, revisión y limpieza manual. */
class ErrorAlertsController extends Controller
{
    public function __construct(private readonly ErrorMonitorService $monitor)
    {
    }

    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
        ], [], ['email' => 'correo del desarrollador']);
        $settings->set('support.email', mb_strtolower(trim($data['email'])));
        $settings->set('support.error_alerts', $request->boolean('enabled'));
        app(AuditService::class)->log('settings_update', null, null, ['support.email' => $data['email'], 'support.error_alerts' => $request->boolean('enabled')], 'Avisos de errores por correo');

        return back()->with('success', 'Guardado. Los avisos de errores y de soporte llegan a '.$data['email'].'.');
    }

    public function test(): RedirectResponse
    {
        $to = $this->monitor->recipient() ?? throw new BusinessException('Primero cargá un correo de desarrollador válido.');
        if (! $this->monitor->mailConfigured()) {
            throw new BusinessException('El servidor no tiene configurado el envío de correo (MAIL_MAILER='.config('mail.default').'). '
                .'Completá en el .env los datos MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD y MAIL_FROM_ADDRESS.');
        }
        try {
            Notification::route('mail', $to)->notify(new TestMail);
        } catch (Throwable $e) {
            throw new BusinessException('No se pudo mandar el correo: '.Str::limit($e->getMessage(), 250).'. Revisá los datos MAIL_ del .env.');
        }

        return back()->with('success', 'Correo de prueba enviado a '.$to.'. Si no llega en unos minutos, revisá la carpeta de spam.');
    }

    public function check(): RedirectResponse
    {
        $r = $this->monitor->check(true);

        return back()->with($r['sent'] || $r['found'] === 0 ? 'success' : 'warning', match (true) {
            $r['found'] === 0 => 'No hay errores nuevos.',
            $r['sent'] => 'Se mandó el aviso con '.$r['found'].' errores.',
            default => $r['found'].' errores sin avisar: '.$r['reason'],
        });
    }

    public function cleanup(): RedirectResponse
    {
        $r = $this->monitor->cleanup();

        return back()->with('success', "Limpieza hecha: {$r['errors']} errores viejos borrados y {$r['files']} archivos de log vaciados.");
    }
}
