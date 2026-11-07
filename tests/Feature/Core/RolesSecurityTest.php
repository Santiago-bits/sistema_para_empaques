<?php

namespace Tests\Feature\Core;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Roles y usuarios a fondo: el administrador del galpón maneja a SU gente, nadie se sube de rango solo, y el
 * dueño del sistema (Super Administrador) queda fuera del alcance de los administradores.
 */
class RolesSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $slug): Role
    {
        return Role::query()->where('slug', $slug)->firstOrFail();
    }

    private function userPayload(array $extra = []): array
    {
        return $extra + ['first_name' => 'Ana', 'last_name' => 'Pérez', 'username' => 'ana'.random_int(100, 999), 'status' => 'active',
            'password' => 'Clave-Segura-123', 'password_confirmation' => 'Clave-Segura-123', 'access_mode' => 'role'];
    }

    /** Un encargado al que le delegaron «Roles y permisos» y «Usuarios», pero que no es administrador. */
    private function delegate(): User
    {
        $role = Role::query()->create(['slug' => 'encargado', 'name' => 'Encargado']);
        $role->permissions()->sync(Permission::query()->whereIn('slug', ['dashboard.view', 'users.view', 'users.manage', 'roles.manage', 'catalogs.view'])->pluck('id'));

        return $this->actingAsRole('encargado');
    }

    public function test_admin_manages_his_employees_but_never_sees_or_touches_the_super_admin(): void
    {
        $super = User::factory()->role('super_admin')->create(['username' => 'desarrollador', 'first_name' => 'Dev']);
        $admin = $this->actingAsRole('admin');

        // Ve y gestiona a sus empleados.
        $this->post(route('admin.users.store'), $this->userPayload(['username' => 'operario1', 'role_id' => $this->role('intake_operator')->id]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('admin.users.index'))->assertOk()->assertSee('operario1')->assertDontSee('desarrollador');

        // Al super administrador: no lo ve, no lo edita, no le cambia la clave ni los permisos.
        $this->get(route('admin.users.show', $super))->assertForbidden();
        $this->get(route('admin.users.edit', $super))->assertForbidden();
        $this->put(route('admin.users.update', $super), $this->userPayload(['username' => 'desarrollador', 'role_id' => $this->role('admin')->id]))->assertForbidden();
        $this->post(route('admin.users.password.reset', $super))->assertForbidden();
        $this->put(route('admin.users.permissions.update', $super), ['overrides' => ['users.manage' => 'revoke']])->assertForbidden();
        $this->assertTrue($super->fresh()->isSuperAdmin());

        // No puede crear ni convertir a nadie en super administrador, ni editar ese rol.
        $this->post(route('admin.users.store'), $this->userPayload(['role_id' => $this->role('super_admin')->id]))->assertSessionHasErrors('role_id');
        $this->get(route('admin.roles.edit', $this->role('super_admin')))->assertForbidden();
        $this->put(route('admin.roles.update', $this->role('super_admin')), ['name' => 'X', 'permissions' => []])->assertForbidden();

        // Ni sus propios permisos individuales.
        $this->get(route('admin.users.permissions', $admin))->assertForbidden();
    }

    public function test_admin_cannot_grant_permissions_reserved_to_the_super_admin(): void
    {
        $this->actingAsRole('admin');
        $role = $this->role('supervisor');
        $this->put(route('admin.roles.update', $role), ['name' => $role->name, 'permissions' => ['dashboard.view', 'backups.restore', 'logs.view']])->assertRedirect();
        $this->assertNotContains('backups.restore', $role->permissions()->pluck('slug')->all());
        $this->assertNotContains('logs.view', $role->permissions()->pluck('slug')->all());
    }

    public function test_a_delegate_cannot_raise_his_own_role_or_give_others_what_he_does_not_have(): void
    {
        $actor = $this->delegate();
        $own = $actor->role;

        // Agregarse a sí mismo Tesorería editando su propio rol: no.
        $this->put(route('admin.roles.update', $own), ['name' => 'Encargado', 'permissions' => ['dashboard.view', 'users.view', 'users.manage', 'roles.manage', 'catalogs.view', 'treasury.view']])
            ->assertSessionHas('error');
        $this->assertFalse($actor->fresh()->can('treasury.view'));

        // Crear un rol nuevo con permisos que no tiene: no.
        $this->post(route('admin.roles.store'), ['name' => 'Cajero', 'permissions' => ['treasury.view', 'cash.manage']])->assertSessionHas('error');
        $this->assertFalse(Role::query()->where('name', 'Cajero')->exists());
        // Con permisos que sí tiene: sí.
        $this->post(route('admin.roles.store'), ['name' => 'Consulta', 'permissions' => ['catalogs.view']])->assertRedirect()->assertSessionHasNoErrors();

        // Dar permisos individuales que no tiene a otro usuario (o a un cómplice): no.
        $other = User::factory()->role('employee')->create();
        $this->put(route('admin.users.permissions.update', $other), ['overrides' => ['treasury.view' => 'grant']])->assertSessionHas('error');
        $this->assertFalse($other->fresh()->can('treasury.view'));
        $this->put(route('admin.users.permissions.update', $other), ['overrides' => ['catalogs.view' => 'grant']])->assertRedirect();
        $this->assertTrue($other->fresh()->can('catalogs.view'));

        // Ni asignar un rol con más permisos (Administrador, Facturación) ni el acceso «total».
        $this->post(route('admin.users.store'), $this->userPayload(['role_id' => $this->role('admin')->id]))->assertSessionHasErrors('role_id');
        $this->post(route('admin.users.store'), $this->userPayload(['role_id' => $this->role('billing')->id]))->assertSessionHasErrors('role_id');
        $this->post(route('admin.users.store'), $this->userPayload(['access_mode' => 'full']))->assertSessionHasErrors('role_id');
        $this->put(route('admin.users.update', $other), $this->userPayload(['username' => $other->username, 'role_id' => $this->role('admin')->id]))->assertSessionHasErrors('role_id');
        $this->assertFalse($other->fresh()->role->slug === 'admin');
    }

    public function test_without_permission_users_and_roles_screens_are_closed(): void
    {
        $this->actingAsRole('intake_operator');
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.roles.index'))->assertForbidden();
        $this->post(route('admin.roles.store'), ['name' => 'X', 'permissions' => ['users.manage']])->assertForbidden();

        // Sólo ver usuarios: no crea ni edita.
        $this->actingWithPermissions(['users.view']);
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.users.create'))->assertForbidden();
        $this->post(route('admin.users.store'), $this->userPayload(['role_id' => $this->role('employee')->id]))->assertForbidden();
    }

    public function test_role_changes_take_effect_and_are_audited(): void
    {
        $this->actingAsRole('admin');
        $worker = User::factory()->role('quality')->create();
        $role = $this->role('quality');
        $this->assertTrue($worker->can('quality.view'));

        $slugs = $role->permissions()->pluck('slug')->reject(fn ($s) => $s === 'quality.view')->values()->all();
        $this->put(route('admin.roles.update', $role), ['name' => $role->name, 'permissions' => $slugs])->assertRedirect();
        $this->assertFalse($worker->fresh()->can('quality.view'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'permissions', 'auditable_type' => 'role', 'auditable_id' => $role->id]);
    }
}
