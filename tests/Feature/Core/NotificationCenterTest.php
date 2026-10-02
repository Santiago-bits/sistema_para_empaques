<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Notifications\PasswordResetRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_marks_and_opens_own_notifications(): void
    {
        $admin = $this->actingAsRole('admin');
        $operator = User::factory()->role('operator')->create();
        $admin->notify(new PasswordResetRequested($operator, '10.0.0.5'));
        $notification = $admin->notifications()->sole();

        $this->get(route('notifications.index'))->assertOk()->assertSee('Pedido de recuperación de contraseña')->assertSee('1 sin leer');

        $this->post(route('notifications.open', $notification->id))->assertRedirect(route('admin.users.show', $operator));
        $this->assertNotNull($notification->fresh()->read_at);

        $this->post(route('notifications.unread', $notification->id))->assertRedirect();
        $this->assertNull($notification->fresh()->read_at);

        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $admin->unreadNotifications()->count());
    }

    public function test_cannot_touch_someone_elses_notifications(): void
    {
        $owner = User::factory()->role('admin')->create();
        $owner->notify(new PasswordResetRequested(User::factory()->role('operator')->create()));
        $notification = $owner->notifications()->sole();

        $this->actingAsRole('operator');
        $this->post(route('notifications.open', $notification->id))->assertNotFound();
        $this->post(route('notifications.unread', $notification->id))->assertNotFound();
        $this->post(route('notifications.read-all'));
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_external_urls_are_never_followed(): void
    {
        $user = $this->actingAsRole('admin');
        $id = (string) Str::uuid();
        $user->notifications()->create(['id' => $id, 'type' => 'x', 'data' => ['title' => 'Trampa', 'url' => 'https://evil.example/login']]);

        $this->post(route('notifications.open', $id))->assertRedirect(route('notifications.index'));
    }
}
