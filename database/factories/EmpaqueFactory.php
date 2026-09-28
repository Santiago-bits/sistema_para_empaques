<?php

namespace Database\Factories;

use App\Enums\EstadoEmpaque;
use App\Models\Empaque;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empaque>
 */
class EmpaqueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Se asigna acá porque los seeders pueden correr sin eventos del modelo.
            'codigo' => Empaque::generarCodigo(),
            'nombre' => 'Caja '.fake()->unique()->numberBetween(100, 999),
            'descripcion' => fake()->optional()->sentence(),
            'estado' => fake()->randomElement(EstadoEmpaque::cases()),
        ];
    }
}
