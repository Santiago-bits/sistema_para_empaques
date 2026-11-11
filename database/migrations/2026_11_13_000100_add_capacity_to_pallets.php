<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Cuántos cajones lleva cada pallet (de 1 a 50). Vacío = el valor de Configuración → Producción. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pallets', 'capacity')) {
            Schema::table('pallets', function (Blueprint $table) {
                $table->unsignedSmallInteger('capacity')->nullable()->after('quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pallets', 'capacity')) {
            Schema::table('pallets', fn (Blueprint $table) => $table->dropColumn('capacity'));
        }
    }
};
