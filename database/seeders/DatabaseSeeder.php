<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos base del sistema. Los datos de demostración se instalan aparte con:
     *   php artisan db:seed --class=DemoSeeder
     * (nunca en una base de producción con datos reales).
     */
    public function run(): void
    {
        $this->call(SystemSeeder::class);
    }
}
