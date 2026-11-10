<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Panel General → Galpones: cada licencia es un galpón con su propio sistema. Se guarda su CUIT y el usuario
 * del administrador general que el desarrollador crea a mano en ese sistema (para tener todo ordenado).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('licenses') || Schema::hasColumn('licenses', 'admin_username')) {
            return;
        }

        Schema::table('licenses', function (Blueprint $table) {
            $table->string('cuit', 13)->nullable()->after('client_name');
            $table->string('admin_username', 60)->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', fn (Blueprint $table) => $table->dropColumn(['cuit', 'admin_username']));
    }
};
