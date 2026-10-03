<?php

namespace Tests\Feature\Core;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\ClientTicket;
use App\Models\License;
use App\Models\User;
use App\Notifications\ClientTicketReceived;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SuperAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = $this->actingAsRole('super_admin');
        $this->post(route('superadmin.confirm.store'), ['password' => 'password'])->assertRedirect(route('superadmin.index'));

        return $user;
    }

    public function test_only_the_super_admin_sees_it_and_must_retype_the_password(): void
    {
        $this->actingAsRole('admin');
        $this->get('/administradorgeneral')->assertNotFound();
        $this->get(route('superadmin.users.index'))->assertNotFound();

        $owner = $this->actingAsRole('super_admin');
        $this->get('/administradorgeneral')->assertRedirect(route('superadmin.confirm'));
        $this->post(route('superadmin.confirm.store'), ['password' => 'otra'])->assertSessionHasErrors('password');
        $this->get(route('superadmin.index'))->assertRedirect(route('superadmin.confirm'));
        $this->assertTrue(AuditLog::query()->where('action', 'superadmin_denied')->exists());

        $this->post(route('superadmin.confirm.store'), ['password' => 'password'])->assertRedirect(route('superadmin.index'));
        $this->get('/administradorgeneral')->assertOk()->assertSee('Administración general')->assertSee('Cifradas')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertTrue(AuditLog::query()->where('action', 'superadmin_access')->where('user_id', $owner->id)->exists());

        // A los 15 minutos vuelve a pedir la contraseña.
        $this->travel(16)->minutes();
        $this->get(route('superadmin.index'))->assertRedirect(route('superadmin.confirm'));
        $this->travelBack();

        // «Salir de la administración» también la vuelve a pedir.
        $this->post(route('superadmin.confirm.store'), ['password' => 'password']);
        $this->post(route('superadmin.lock'))->assertRedirect(route('home'));
        $this->get(route('superadmin.index'))->assertRedirect(route('superadmin.confirm'));
    }

    public function test_sees_all_user_data_but_never_the_password_and_can_help(): void
    {
        $this->superAdmin();
        $employee = User::factory()->role('employee')->create([
            'first_name' => 'Juana', 'last_name' => 'Gómez', 'username' => 'juana', 'email' => 'juana@example.com',
            'phone' => '2604 555-111', 'dni' => '30111222', 'password' => 'ClaveSecreta123',
        ]);
        $hash = $employee->fresh()->getAuthPassword();
        $this->assertStringStartsWith('$2y$', $hash); // bcrypt
        $this->assertNotSame('ClaveSecreta123', $hash);

        $this->get(route('superadmin.users.index', ['q' => 'juana@example']))->assertOk()
            ->assertSee('Gómez')->assertSee('juana@example.com')->assertSee('2604 555-111');
        $page = $this->get(route('superadmin.users.show', $employee))->assertOk()->assertSee('30111222')->assertSee('Cifrada (no se puede ver)');
        $page->assertDontSee($hash, false)->assertDontSee('ClaveSecreta123');
        $this->assertTrue(AuditLog::query()->where('action', 'superadmin_view_user')->where('auditable_id', $employee->id)->exists());

        // Se olvidó la contraseña: temporal, mostrada una vez, cifrada en la base y con cambio obligatorio.
        $response = $this->post(route('superadmin.users.password', $employee))->assertRedirect(route('superadmin.users.show', $employee));
        $temporary = session('temporary_password');
        $this->assertNotEmpty($temporary);
        $employee->refresh();
        $this->assertTrue($employee->must_change_password);
        $this->assertTrue(Hash::check($temporary, $employee->getAuthPassword()));
        $this->assertNotSame($temporary, $employee->getAuthPassword());

        // Se olvidó el email / usuario: se corrige con motivo y queda auditado.
        $this->put(route('superadmin.users.access', $employee), ['username' => 'juana_gomez', 'email' => 'nuevo@example.com', 'phone' => '', 'reason' => 'No recordaba su email'])
            ->assertSessionHasNoErrors();
        $this->assertSame('juana_gomez', $employee->fresh()->username);
        $this->assertSame('No recordaba su email', AuditLog::query()->where('action', 'access_data')->latest('id')->value('reason'));
        $this->put(route('superadmin.users.access', $employee), ['username' => 'juana_gomez', 'reason' => 'x'])->assertSessionHasErrors('reason');

        // Baja con motivo; no puede darse de baja a sí mismo.
        $this->put(route('superadmin.users.status', $employee), ['status' => 'inactive', 'reason' => 'Dejó el galpón'])->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Inactive, $employee->fresh()->status);
        $this->post(route('superadmin.users.sessions', $employee))->assertSessionHas('success');
    }

    public function test_cannot_lock_out_the_last_super_admin(): void
    {
        $owner = $this->superAdmin();
        $this->put(route('superadmin.users.status', $owner), ['status' => 'inactive', 'reason' => 'Prueba de baja'])->assertSessionHas('error');
        $this->post(route('superadmin.users.password', $owner))->assertSessionHas('error');
        $this->assertSame(UserStatus::Active, $owner->fresh()->status);
    }

    public function test_client_management_payments_and_support_notifications(): void
    {
        Notification::fake();
        $owner = $this->superAdmin();

        // Sin activar, clientes no existe.
        $this->get(route('superadmin.clients.index'))->assertNotFound();
        $this->put(route('superadmin.central'), ['enable' => 1])->assertSessionHas('success');
        $this->assertTrue((bool) app(SettingsService::class)->get('system.central_panel'));
        config(['galpon.central.mode' => true]); // en una petición nueva lo aplica el arranque

        $license = License::query()->create(['installation_id' => 'finca-1', 'client_name' => 'Empaque Los Álamos', 'license_key' => 'K-1', 'starts_on' => today()]);
        $this->assertSame('free', $license->paymentStatus());

        $this->put(route('superadmin.clients.fee', $license), ['monthly_fee' => '50000', 'fee_currency' => 'ARS'])->assertSessionHas('success');
        $this->assertSame('never', $license->fresh()->paymentStatus());
        $this->get(route('superadmin.clients.index', ['payment' => 'never']))->assertOk()->assertSee('Empaque Los Álamos')->assertSee('Sin pagos');

        // Paga 2 meses hoy: cubre hasta dentro de 2 meses menos un día.
        $this->post(route('superadmin.clients.payments.store', $license), ['amount' => '100.000', 'currency' => 'ARS', 'paid_at' => today()->toDateString(), 'months' => 2, 'method' => 'transfer', 'reference' => 'TRF-1'])
            ->assertSessionHasNoErrors();
        $license->refresh();
        $this->assertSame(today()->addMonthsNoOverflow(2)->subDay()->toDateString(), $license->paid_until->toDateString());
        $this->assertSame('ok', $license->paymentStatus());
        $this->assertSame('100000.00', $license->payments()->value('amount'));

        // El siguiente pago continúa donde terminó el anterior.
        $this->post(route('superadmin.clients.payments.store', $license), ['amount' => '50000', 'currency' => 'ARS', 'paid_at' => today()->toDateString(), 'months' => 1, 'method' => 'cash']);
        $second = $license->payments()->latest('id')->first();
        $this->assertSame(today()->addMonthsNoOverflow(2)->toDateString(), $second->period_from->toDateString());

        // Anular vuelve la cobertura al pago anterior.
        $this->post(route('superadmin.clients.payments.void', [$license, $second]), ['reason' => 'Se cargó dos veces'])->assertSessionHas('success');
        $this->assertSame(today()->addMonthsNoOverflow(2)->subDay()->toDateString(), $license->fresh()->paid_until->toDateString());
        $this->post(route('superadmin.clients.payments.void', [$license, $second]), ['reason' => 'Otra vez'])->assertSessionHas('error');

        // Vencido.
        $this->travel(3)->months();
        $this->assertSame('overdue', $license->fresh()->paymentStatus());
        $this->travelBack();

        $this->get(route('superadmin.index'))->assertOk()->assertSee('Cobrado este mes')->assertSee('Empaque Los Álamos');
        $this->get(route('superadmin.clients.show', $license))->assertOk()->assertSee('TRF-1')->assertSee('Se cargó dos veces');

        // Pedido de soporte de un cliente: avisa al super admin.
        $ticket = ClientTicket::query()->create(['license_id' => $license->id, 'remote_number' => 'T-1', 'subject' => 'No imprime etiquetas', 'description' => 'Ayuda', 'last_message_at' => now()]);
        $owner->notify(new ClientTicketReceived($ticket->id, 'Empaque Los Álamos: No imprime etiquetas'));
        Notification::assertSentTo($owner, ClientTicketReceived::class);
        $this->get(route('superadmin.index'))->assertSee('No imprime etiquetas');
    }

    /** Escribir /administradorgeneral sin sesión: ingreso con aviso y, al ingresar, vuelve ahí solo. */
    public function test_typing_the_address_logged_out_leads_there_after_login(): void
    {
        $owner = User::factory()->role('super_admin')->create(['username' => 'duenio']);

        $this->get('/administradorgeneral')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Administración general')->assertSee('Super Administrador');

        $this->post(route('login.store'), ['login' => 'duenio', 'password' => 'password'])->assertRedirect(url('/administradorgeneral'));
        $this->get('/administradorgeneral')->assertRedirect(route('superadmin.confirm'));
        $this->post(route('superadmin.confirm.store'), ['password' => 'password'])->assertRedirect(route('superadmin.index'));
        $this->get(route('superadmin.index'))->assertOk();
        $this->assertAuthenticatedAs($owner);
    }

    public function test_owner_recovers_access_with_the_database_password(): void
    {
        $owner = User::factory()->role('super_admin')->create(['username' => 'duenio']);
        config(['database.connections.'.config('database.default').'.password' => 'clave-db']);

        $this->get(route('login'))->assertSee('¿Sos el dueño');
        $this->get(route('owner.recovery'))->assertOk();
        $this->post(route('owner.recovery.store'), ['db_password' => 'otra', 'password' => 'NuevaClave1', 'password_confirmation' => 'NuevaClave1'])
            ->assertSessionHasErrors('db_password');
        $this->assertTrue(Hash::check('password', $owner->fresh()->getAuthPassword()));

        $this->post(route('owner.recovery.store'), ['db_password' => 'clave-db', 'password' => 'NuevaClave1', 'password_confirmation' => 'NuevaClave1'])
            ->assertRedirect(route('login'))->assertSessionHas('success');
        $this->assertTrue(Hash::check('NuevaClave1', $owner->fresh()->getAuthPassword()));
        $this->get(route('login'))->assertSee('duenio');

        $this->post(route('login.store'), ['login' => 'duenio', 'password' => 'NuevaClave1'])->assertRedirect(route('home'));
        $this->post(route('logout'));

        // También puede elegir un usuario nuevo (que no use otra persona).
        User::factory()->create(['username' => 'ocupado']);
        $this->post(route('owner.recovery.store'), ['db_password' => 'clave-db', 'username' => 'ocupado', 'password' => 'OtraClave22', 'password_confirmation' => 'OtraClave22'])
            ->assertSessionHasErrors('username');
        $this->post(route('owner.recovery.store'), ['db_password' => 'clave-db', 'username' => 'leonardo', 'password' => 'OtraClave22', 'password_confirmation' => 'OtraClave22'])
            ->assertRedirect(route('login'));
        $this->assertSame('leonardo', $owner->fresh()->username);
        $this->post(route('login.store'), ['login' => 'leonardo', 'password' => 'OtraClave22'])->assertRedirect(route('home'));
    }

    public function test_sample_data_button_loads_at_least_ten_of_everything_once(): void
    {
        $this->superAdmin();
        $users = User::query()->count();

        $this->get(route('superadmin.index'))->assertSee('Cargar datos de ejemplo');
        $this->post(route('superadmin.sample'))->assertRedirect(route('superadmin.index'))->assertSessionHas('success');

        foreach ([\App\Models\Producer::class, \App\Models\Owner::class, \App\Models\Client::class, \App\Models\Destination::class, \App\Models\Provider::class,
            \App\Models\Transporter::class, \App\Models\Truck::class, \App\Models\Driver::class, \App\Models\Variety::class, \App\Models\Size::class,
            \App\Models\ColdRoom::class, \App\Models\Supply::class, \App\Models\Machine::class, \App\Models\Incident::class, \App\Models\Cost::class,
            \App\Models\ProductionStoppage::class, \App\Models\ContainerType::class, \App\Models\Employee::class, \App\Models\ExchangeRate::class,
            \App\Models\Check::class, \App\Models\CashMovement::class, \App\Models\Lot::class, \App\Models\Pallet::class, \App\Models\Crate::class,
            \App\Models\Packer::class, \App\Models\InventoryMovement::class] as $model) {
            $this->assertGreaterThanOrEqual(10, $model::query()->count(), class_basename($model).' debería tener al menos 10 ejemplos');
        }
        $this->assertGreaterThanOrEqual(8, \App\Models\Load::query()->count());
        $this->assertGreaterThanOrEqual(3, \App\Models\Invoice::query()->count());
        // No crea usuarios (en un servidor real serían cuentas con contraseña conocida).
        $this->assertSame($users, User::query()->count());

        // Una sola vez.
        $this->post(route('superadmin.sample'))->assertSessionHas('error');
        $this->get(route('superadmin.index'))->assertDontSee('Cargar datos de ejemplo');
    }

    public function test_support_notification_goes_by_email_when_mail_is_configured(): void
    {
        $owner = User::factory()->role('super_admin')->create(['email' => 'duenio@example.com']);
        $notification = new ClientTicketReceived(1, 'Prueba');
        $this->assertSame(['database'], $notification->via($owner));
        config(['mail.default' => 'smtp']);
        $this->assertSame(['database', 'mail'], $notification->via($owner));
    }
}
