<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Recupera el acceso creando un Super Administrador desde la consola del servidor. */
class CreateAdmin extends Command
{
    protected $signature = 'galpon:create-admin';

    protected $description = 'Crea un usuario Super Administrador (por si se perdió el acceso al sistema)';

    public function handle(AuditService $audit): int
    {
        $role = Role::query()->where('slug', Role::SUPER_ADMIN)->first();
        if (! $role) {
            $this->error('No existe el rol Super Administrador. Ejecutá primero: php artisan db:seed --class=SystemSeeder');

            return self::FAILURE;
        }

        $this->info('Creación de un Super Administrador. Este usuario tendrá acceso total al sistema.');

        $firstName = $this->askValid('Nombre', ['required', 'string', 'max:80']);
        $lastName = $this->askValid('Apellido', ['required', 'string', 'max:80']);
        $username = $this->askValid('Usuario (para ingresar)', ['required', 'string', 'max:60', 'alpha_dash', 'unique:users,username']);
        $email = $this->askValid('Email (opcional)', ['nullable', 'email', 'max:255', 'unique:users,email'], allowEmpty: true);

        $password = null;
        for ($attempt = 0; $attempt < 3 && $password === null; $attempt++) {
            $first = (string) $this->secret('Contraseña (no se muestra al escribir)');
            $confirm = (string) $this->secret('Repetí la contraseña');

            $validator = Validator::make(
                ['password' => $first, 'password_confirmation' => $confirm],
                ['password' => ['required', 'confirmed', Password::defaults()]],
            );
            if ($validator->fails()) {
                $this->error($validator->errors()->first('password'));
                continue;
            }
            $password = $first;
        }

        if ($password === null) {
            $this->error('No se creó el usuario.');

            return self::FAILURE;
        }

        if (! $this->confirm('¿Crear el Super Administrador "'.$username.'"?', true)) {
            $this->warn('Operación cancelada.');

            return self::FAILURE;
        }

        $user = User::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'email' => $email ?: null,
            'role_id' => $role->id,
            'status' => UserStatus::Active->value,
            'password' => $password, // cast "hashed"
        ]);

        $audit->log('create', $user, null, ['username' => $username], 'Super Administrador creado desde la consola (galpon:create-admin)');

        $this->info('Listo. Ya podés ingresar con el usuario "'.$user->username.'".');

        return self::SUCCESS;
    }

    private function askValid(string $question, array $rules, bool $allowEmpty = false): ?string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $value = trim((string) $this->ask($question));
            if ($allowEmpty && $value === '') {
                return null;
            }
            $validator = Validator::make(['value' => $value], ['value' => $rules]);
            if (! $validator->fails()) {
                return $value;
            }
            $this->error($validator->errors()->first('value'));
        }

        throw new \RuntimeException('Demasiados intentos inválidos.');
    }
}
