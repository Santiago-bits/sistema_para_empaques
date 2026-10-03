<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Reason;
use App\Models\Role;
use App\Models\Season;
use App\Models\Sequence;
use App\Models\Shift;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\ModuleService;
use App\Services\SequenceService;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;

/**
 * Datos base indispensables para que el sistema funcione (idempotente: se puede
 * ejecutar en cada actualización sin duplicar ni pisar configuraciones del cliente).
 */
class SystemSeeder extends Seeder
{
    public function run(): void
    {
        app(AuditService::class)->muted(function () {
            $this->companyAndWarehouse();
            $this->permissionsAndRoles();
            $this->modules();
            $this->sequences();
            $this->reasons();
            $this->shifts();
            $this->grades();
            $this->season();
        });

        app(ModuleService::class)->flush();
    }

    private function companyAndWarehouse(): void
    {
        // Sin forzar el id: el instalador corre este seeder fuera de `db:seed` (con protección de asignación masiva).
        $company = Company::query()->orderBy('id')->first() ?? Company::query()->create(['name' => 'Mi Empresa']);
        Warehouse::query()->firstOrCreate(['code' => 'GAL-A'], ['company_id' => $company->id, 'name' => 'Galpón A']);
    }

    private function permissionsAndRoles(): void
    {
        $new = [];
        foreach (config('permissions.permissions') as $module => $permissions) {
            foreach ($permissions as $slug => $name) {
                $permission = Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'module' => $module]);
                if ($permission->wasRecentlyCreated) {
                    $new[$slug] = $permission->id;
                }
            }
        }

        foreach (config('permissions.roles') as $slug => $definition) {
            $role = Role::query()->firstOrCreate(['slug' => $slug], [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_system' => true,
            ]);

            // Sólo se asignan permisos por defecto a roles recién creados:
            // si el administrador personalizó un rol, no se pisa.
            if ($role->wasRecentlyCreated) {
                $ids = Permission::query()->whereIn('slug', PermissionRegistry::expand($definition['permissions']))->pluck('id');
                $role->permissions()->sync($ids);
            } elseif ($new !== []) {
                // Actualización con permisos nuevos (p. ej. un módulo agregado): se suman a los roles del
                // sistema que los tendrían por defecto, sin tocar lo que el administrador personalizó.
                $grant = array_intersect_key($new, array_flip(PermissionRegistry::expand($definition['permissions'])));
                $role->permissions()->syncWithoutDetaching(array_values($grant));
            }
        }
    }

    private function modules(): void
    {
        $sort = 0;
        foreach (ModuleService::CATALOG as $key => [$name, $description, $enabled, $core]) {
            Module::query()->firstOrCreate(['key' => $key], [
                'name' => $name,
                'description' => $description,
                'enabled' => $enabled,
                'is_core' => $core,
                'sort' => $sort++,
            ]);
        }
    }

    private function sequences(): void
    {
        foreach (SequenceService::DEFAULTS as $key => [$prefix, $padding]) {
            Sequence::query()->firstOrCreate(['key' => $key], ['prefix' => $prefix, 'padding' => $padding, 'next_number' => 1]);
        }
    }

    private function reasons(): void
    {
        $reasons = [
            'reject' => ['ROT' => 'Podredumbre', 'HIT' => 'Golpe', 'SIZE' => 'Tamaño incorrecto', 'QUAL' => 'Mala calidad',
                'MECH' => 'Daño mecánico', 'CONT' => 'Contaminación', 'OTHER' => 'Otro'],
            'stoppage' => ['NOFRUIT' => 'Falta de fruta', 'MACHINE' => 'Falla de máquina', 'STAFF' => 'Falta de personal',
                'MAINT' => 'Mantenimiento', 'POWER' => 'Problemas eléctricos', 'CLEAN' => 'Limpieza', 'OTHER' => 'Otro'],
        ];
        foreach ($reasons as $type => $items) {
            foreach ($items as $code => $name) {
                Reason::query()->firstOrCreate(['type' => $type, 'code' => $code], ['name' => $name]);
            }
        }
    }

    private function grades(): void
    {
        if (\App\Models\Grade::query()->exists()) {
            return;
        }
        foreach ([['EXT', 'Extra'], ['ELE', 'Elegido'], ['COM', 'Comercial']] as $i => [$code, $name]) {
            \App\Models\Grade::query()->create(['code' => $code, 'name' => $name, 'sort_order' => $i + 1]);
        }
    }

    private function shifts(): void
    {
        if (Shift::query()->exists()) {
            return;
        }
        Shift::query()->create(['code' => 'M', 'name' => 'Mañana', 'starts_at' => '06:00', 'ends_at' => '14:00']);
        Shift::query()->create(['code' => 'T', 'name' => 'Tarde', 'starts_at' => '14:00', 'ends_at' => '22:00']);
        Shift::query()->create(['code' => 'N', 'name' => 'Noche', 'starts_at' => '22:00', 'ends_at' => '06:00']);
    }

    private function season(): void
    {
        if (Season::query()->exists()) {
            return;
        }
        $year = now()->year;
        Season::query()->create([
            'name' => 'Temporada '.$year,
            'starts_on' => "{$year}-01-01",
            'ends_on' => "{$year}-12-31",
            'is_current' => true,
        ]);
    }
}
