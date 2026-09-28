<?php

namespace Database\Seeders;

use App\Models\Empaque;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmpaqueSeeder extends Seeder
{
    /**
     * Crea empaques de prueba asociados al primer usuario.
     */
    public function run(): void
    {
        Empaque::factory()
            ->count(15)
            ->for(User::first(), 'usuario')
            ->create();
    }
}
