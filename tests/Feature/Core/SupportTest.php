<?php

namespace Tests\Feature\Core;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketMail;
use App\Notifications\SupportTicketUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creates_ticket_and_developer_answers(): void
    {
        Notification::fake();
        $developer = User::factory()->role('super_admin')->create();
        $user = $this->actingAsRole('loads_operator');

        $this->get(route('support.index'))->assertOk();
        $this->get(route('support.create'))->assertOk();
        $this->post(route('support.store'), ['subject' => 'No imprime el remito', 'description' => 'Aparece ERR-20261001-ABCDE', 'priority' => 'high'])
            ->assertRedirect();
        $ticket = SupportTicket::query()->sole();
        $this->assertStringStartsWith('TK-', $ticket->number);
        Notification::assertSentTo($developer, SupportTicketUpdated::class);

        $this->actingAs($developer);
        $this->get(route('support.index'))->assertSee('No imprime el remito');
        $this->post(route('support.reply', $ticket), ['body' => 'Ya está corregido'])->assertRedirect();
        $this->post(route('support.status', $ticket), ['status' => 'resolved']);
        Notification::assertSentTo($user, SupportTicketUpdated::class);

        // El usuario responde un ticket resuelto: vuelve a revisión.
        $this->actingAs($user);
        $this->get(route('support.show', $ticket))->assertOk()->assertSee('Ya está corregido')->assertSee('Soporte');
        $this->post(route('support.reply', $ticket), ['body' => 'Sigue fallando'])->assertRedirect();
        $this->assertSame('review', $ticket->fresh()->status);

        $this->post(route('support.status', $ticket), ['status' => 'closed'])->assertRedirect();
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->post(route('support.reply', $ticket), ['body' => 'otra'])->assertSessionHas('error');
    }

    public function test_users_cannot_see_or_manage_others_tickets(): void
    {
        $owner = User::factory()->role('quality')->create();
        $ticket = app(\App\Services\SupportService::class)->create($owner, ['subject' => 'Privado', 'description' => 'x', 'priority' => 'low']);

        $this->actingAsRole('loads_operator');
        $this->get(route('support.index'))->assertDontSee('Privado');
        $this->get(route('support.show', $ticket))->assertNotFound();
        $this->post(route('support.reply', $ticket), ['body' => 'hola'])->assertNotFound();
        $this->post(route('support.status', $ticket), ['status' => 'closed'])->assertNotFound();

        // El dueño sólo puede cerrarlo, no pasarlo a "en desarrollo".
        $this->actingAs($owner);
        $this->post(route('support.status', $ticket), ['status' => 'development'])->assertForbidden();
    }

    public function test_new_ticket_and_galpon_replies_are_emailed_to_support(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $developer = User::factory()->role('super_admin')->create();
        $user = $this->actingAsRole('loads_operator');
        $user->update(['email' => 'operador@galpon.test']);

        $this->post(route('support.store'), ['subject' => 'No imprime el remito', 'description' => "Paso 1\n\nAparece ERR-20261001-ABCDE", 'priority' => 'high']);
        $ticket = SupportTicket::query()->firstOrFail();

        Notification::assertSentOnDemand(SupportTicketMail::class, function (SupportTicketMail $n, array $channels, object $notifiable) use ($ticket) {
            $mail = $n->toMail($notifiable);
            $text = implode("\n", array_map('strval', [...$mail->introLines, ...$mail->outroLines]));

            return $notifiable->routes['mail'] === 'holabaseocho@gmail.com'
                && str_contains($mail->subject, $ticket->number) && str_contains($mail->subject, 'No imprime el remito')
                && str_contains($text, 'ERR-20261001-ABCDE') && str_contains($text, 'Alta')
                && $mail->replyTo === [['operador@galpon.test', $n->author->full_name]];
        });

        // La respuesta del galpón también llega; la del soporte no (la escribió quien recibe los correos).
        $this->post(route('support.reply', $ticket), ['body' => 'Sigue igual']);
        Notification::assertSentOnDemandTimes(SupportTicketMail::class, 2);
        $this->actingAs($developer)->post(route('support.reply', $ticket), ['body' => 'Ya está']);
        Notification::assertSentOnDemandTimes(SupportTicketMail::class, 2);

        // Sin email de soporte configurado no se manda nada.
        app(\App\Services\SettingsService::class)->set('support.email', '');
        $this->actingAs($user)->post(route('support.store'), ['subject' => 'Otro', 'description' => 'x', 'priority' => 'low']);
        Notification::assertSentOnDemandTimes(SupportTicketMail::class, 2);
    }

    public function test_validation_and_permission(): void
    {
        $this->actingAsRole('loads_operator');
        $this->post(route('support.store'), ['subject' => '', 'description' => '', 'priority' => 'urgentisima'])
            ->assertSessionHasErrors(['subject', 'description', 'priority']);

        $this->actingWithPermissions(['crates.view']);
        $this->get(route('support.index'))->assertForbidden();
    }
}
