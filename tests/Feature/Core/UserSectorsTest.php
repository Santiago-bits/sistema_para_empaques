<?php

namespace Tests\Feature\Core;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Sectors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSectorsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Laura', 'last_name' => 'Ríos', 'username' => 'laura', 'status' => 'active',
            'password' => 'Clave1234', 'password_confirmation' => 'Clave1234', 'access_mode' => 'sectors',
            'sectors' => ['labels', 'treasury'],
        ], $overrides);
    }

    public function test_sector_permissions_reference_existing_permissions(): void
    {
        $known = Permission::query()->pluck('slug')->all();
        foreach (Sectors::all() as $key => $sector) {
            $this->assertSame([], array_values(array_diff($sector['permissions'], $known)), "Sector {$key}");
        }
    }

    public function test_owner_creates_employee_with_sectors(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('admin.users.create'))->assertOk()->assertSee('Romaneo y producción')->assertSee('Contabilidad y tesorería');

        $this->post(route('admin.users.store'), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $user = User::query()->where('username', 'laura')->firstOrFail();

        $this->assertSame(Sectors::ROLE, $user->role->slug);
        $this->assertTrue($user->can('labels.print'));
        $this->assertTrue($user->can('cash.manage'));
        $this->assertFalse($user->can('production.scan'));
        $this->assertFalse($user->can('users.manage'));
        $this->assertEqualsCanonicalizing(['labels', 'treasury'], array_intersect(Sectors::of($user), ['labels', 'treasury', 'weighing']));

        // El empleado entra a sus sectores y no a los demás.
        $this->actingAs($user);
        $this->get(route('cash.index'))->assertOk();
        $this->get(route('production.scan'))->assertForbidden();
        $menu = $this->get(route('dashboard'))->assertOk();
        $menu->assertSee(route('cash.index'), false)->assertDontSee('href="'.route('production.scan').'"', false);
    }

    public function test_changing_sectors_replaces_permissions_and_switching_to_role_clears_them(): void
    {
        $admin = $this->actingAsRole('admin');
        $this->post(route('admin.users.store'), $this->payload());
        $user = User::query()->where('username', 'laura')->firstOrFail();

        $this->put(route('admin.users.update', $user), $this->payload(['sectors' => ['weighing'], 'password' => '', 'password_confirmation' => '']))
            ->assertSessionHasNoErrors();
        $user = $user->fresh();
        $this->assertTrue($user->can('production.scan'));
        $this->assertFalse($user->can('cash.manage'));
        $this->get(route('admin.users.show', $user))->assertOk()->assertSee('Romaneo y producción');

        $this->put(route('admin.users.update', $user), $this->payload(['access_mode' => 'full', 'password' => '', 'password_confirmation' => '']))
            ->assertSessionHasNoErrors();
        $user = $user->fresh();
        $this->assertSame('admin', $user->role->slug);
        $this->assertSame(0, $user->permissionOverrides()->count());
        $this->assertTrue($user->can('users.manage'));
    }

    public function test_sectors_are_required_and_cannot_exceed_the_editor(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('admin.users.store'), $this->payload(['sectors' => []]))->assertSessionHasErrors('sectors');

        // Alguien que sólo maneja usuarios y tiene el sector Etiquetas no puede dar Contabilidad.
        $editor = $this->actingWithPermissions(array_merge(['users.view', 'users.manage'], config('sectors.labels.permissions')));
        $this->post(route('admin.users.store'), $this->payload(['username' => 'otro', 'sectors' => ['labels', 'treasury']]))->assertRedirect();
        $created = User::query()->where('username', 'otro')->firstOrFail();
        $this->assertTrue($created->can('labels.print'));
        $this->assertFalse($created->can('cash.manage'));
        $this->assertFalse($editor->is($created));
    }

    public function test_user_manager_cannot_grant_a_role_above_own_permissions(): void
    {
        $this->actingWithPermissions(['users.view', 'users.manage', 'labels.print']);
        $this->post(route('admin.users.store'), $this->payload(['username' => 'jefe', 'access_mode' => 'full']))->assertSessionHasErrors('role_id');
        $this->post(route('admin.users.store'), $this->payload(['username' => 'jefe', 'access_mode' => 'role',
            'role_id' => Role::query()->where('slug', 'billing')->value('id')]))->assertSessionHasErrors('role_id');
        $this->assertFalse(User::query()->where('username', 'jefe')->exists());
    }

    public function test_admin_cannot_change_own_access(): void
    {
        $admin = $this->actingAsRole('admin', ['username' => 'duenio']);
        $this->put(route('admin.users.update', $admin), [
            'first_name' => 'Dueño', 'last_name' => 'Galpón', 'username' => 'duenio', 'status' => 'active',
            'access_mode' => 'role', 'role_id' => Role::query()->where('slug', 'super_admin')->value('id'),
        ])->assertSessionHasErrors('role_id');

        $this->put(route('admin.users.update', $admin), [
            'first_name' => 'Dueño', 'last_name' => 'Galpón', 'username' => 'duenio', 'status' => 'active',
            'access_mode' => 'sectors', 'sectors' => ['labels'],
        ])->assertSessionHas('error');
        $this->assertSame('admin', $admin->fresh()->role->slug);
    }

    /** El casillero «Puesto fijo de escaneo» ya no está: los accesos se eligen con los sectores. */
    public function test_user_form_has_no_kiosk_checkbox_and_editing_keeps_the_previous_value(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('admin.users.create'))->assertOk()->assertDontSee('kiosk_mode', false)->assertDontSee('Puesto fijo de escaneo');

        $user = \App\Models\User::factory()->role('employee')->create(['username' => 'laura', 'kiosk_mode' => true]);
        $this->put(route('admin.users.update', $user), $this->payload(['password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->kiosk_mode);
    }
}
