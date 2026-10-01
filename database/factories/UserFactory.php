<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'username' => fake()->unique()->userName().Str::random(3),
            'dni' => (string) fake()->unique()->numberBetween(20000000, 45000000),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /** Asigna un rol por slug (debe existir: ejecutar SystemSeeder antes). */
    public function role(string $slug): static
    {
        return $this->state(fn () => ['role_id' => Role::query()->where('slug', $slug)->value('id')]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive', 'deactivated_at' => now()]);
    }
}
