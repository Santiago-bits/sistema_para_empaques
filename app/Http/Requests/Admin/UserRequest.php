<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.manage');
    }

    protected function prepareForValidation(): void
    {
        // DNI y CUIT se guardan sin puntos ni guiones para poder usarlos como login.
        $this->merge([
            'dni' => $this->filled('dni') ? preg_replace('/\D/', '', (string) $this->input('dni')) : null,
            'cuit' => $this->filled('cuit') ? preg_replace('/\D/', '', (string) $this->input('cuit')) : null,
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
            'internal_code' => $this->filled('internal_code') ? trim((string) $this->input('internal_code')) : null,
        ]);

        // Acceso: por sectores (rol base Empleado + permisos de cada sector), total (Administrador) o un rol elegido.
        $mode = $this->input('access_mode') ?: 'role';
        $this->merge(['access_mode' => $mode]);
        if ($mode === 'sectors') {
            $this->merge(['role_id' => Role::query()->where('slug', \App\Support\Sectors::ROLE)->value('id')]);
        } elseif ($mode === 'full') {
            $this->merge(['role_id' => Role::query()->where('slug', 'admin')->value('id')]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;
        $unique = fn (string $column) => Rule::unique('users', $column)->ignore($id);

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', $unique('username')],
            'dni' => ['nullable', 'digits_between:7,9', $unique('dni')],
            'cuit' => ['nullable', 'digits:11', $unique('cuit')],
            'internal_code' => ['nullable', 'string', 'max:30', $unique('internal_code')],
            'email' => ['nullable', 'email', 'max:255', $unique('email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'role_id' => ['required', 'exists:roles,id', function ($attribute, $value, $fail) {
                $isSuper = Role::query()->whereKey($value)->value('slug') === Role::SUPER_ADMIN;
                if ($isSuper && ! $this->user()->isSuperAdmin()) {
                    $fail('Sólo un Super Administrador puede asignar ese rol.');

                    return;
                }
                // Nadie da un rol con permisos que él mismo no tiene (evita escalar privilegios).
                $actor = $this->user();
                $unchanged = (int) $this->route('user')?->role_id === (int) $value;
                // El rol base «Empleado» sólo trae tablero, alertas y soporte: los sectores se controlan aparte.
                $isBase = Role::query()->whereKey($value)->value('slug') === \App\Support\Sectors::ROLE;
                if (! $actor->isSuperAdmin() && ! $unchanged && ! $isBase) {
                    $missing = Role::query()->with('permissions:id,slug')->find($value)?->permissions->pluck('slug')
                        ->diff($actor->permissionSlugs()) ?? collect();
                    if ($missing->isNotEmpty()) {
                        $fail('No podés asignar un rol con permisos que vos no tenés ('.$missing->take(3)->join(', ').($missing->count() > 3 ? '…' : '').').');
                    }
                }
            }],
            'access_mode' => ['required', Rule::in(['sectors', 'full', 'role'])],
            'sectors' => [Rule::requiredIf($this->input('access_mode') === 'sectors'), 'array'],
            'sectors.*' => ['string', Rule::in(array_keys(\App\Support\Sectors::all()))],
            'packer_id' => ['nullable', 'exists:packers,id'],
            'owner_id' => ['nullable', 'exists:owners,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'kiosk_mode' => ['boolean'],
            'warehouses' => ['nullable', 'array'],
            'warehouses.*' => ['integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'password' => [$id ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'nombre', 'last_name' => 'apellido', 'username' => 'usuario', 'internal_code' => 'código interno',
            'role_id' => 'rol', 'access_mode' => 'tipo de acceso', 'sectors' => 'sectores', 'packer_id' => 'embalador', 'status' => 'estado', 'password' => 'contraseña', 'phone' => 'teléfono',
        ];
    }
}
