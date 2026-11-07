<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Aviso al desarrollador: errores nuevos del sistema, agrupados por causa (más frecuentes primero). */
class ErrorDigestMail extends Notification
{
    /** @param  list<array{title: string, where: string, kind: string, count: int, first: string, last: string, code: ?string, url: ?string}>  $groups */
    public function __construct(public readonly array $groups, public readonly int $total, public readonly int $distinct)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = (string) setting('company.name', config('app.name'));
        $mail = (new MailMessage)
            ->error()
            ->subject('⚠ '.$this->total.' '.($this->total === 1 ? 'error' : 'errores').' en '.$company)
            ->greeting('Errores nuevos en '.$company)
            ->line('Sistema: '.config('app.url').' · versión '.config('galpon.version').' · '.now()->format('d/m/Y H:i'))
            ->line($this->total.' '.($this->total === 1 ? 'error' : 'errores').' de '.$this->distinct.' '.($this->distinct === 1 ? 'tipo' : 'tipos distintos').':');

        foreach ($this->groups as $i => $g) {
            $when = Carbon::parse($g['last'])->format('d/m H:i');
            $mail->line('**'.($i + 1).'. '.$g['title'].'**');
            $mail->line(trim(
                ($g['count'] > 1 ? $g['count'].' veces · ' : '').'último: '.$when
                .($g['code'] ? ' · código '.$g['code'] : '')
                .($g['where'] ? ' · '.$g['where'] : '')
                .($g['url'] && $g['url'] !== 'console' ? ' · '.$g['url'] : ($g['url'] === 'console' ? ' · tarea programada / consola' : ''))
            ));
        }
        if ($this->distinct > count($this->groups)) {
            $mail->line('…y '.($this->distinct - count($this->groups)).' tipos más.');
        }

        return $mail->action('Ver los errores en el sistema', route('developer.errors'))
            ->line('Este aviso se manda solo (como mucho uno cada 15 minutos). Se apaga en Herramientas técnicas.');
    }
}
