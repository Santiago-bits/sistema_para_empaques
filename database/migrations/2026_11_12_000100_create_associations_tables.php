<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Asociaciones de citricultores (Chajarí, Villa del Rosario, Santa Ana…). Lista común a todo el sistema (la carga el
 * Super Administrador); cada galpón puede pertenecer a una o a varias. Salen impresas en lo que emite el galpón.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('associations')) {
            Schema::create('associations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('short_name', 60)->nullable();
                $table->string('cuit', 13)->nullable();
                $table->string('locality', 120)->nullable();
                $table->string('province', 120)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->unique('name');
            });
        }
        if (! Schema::hasTable('association_warehouse')) {
            Schema::create('association_warehouse', function (Blueprint $table) {
                $table->id();
                $table->foreignId('association_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['association_id', 'warehouse_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('association_warehouse');
        Schema::dropIfExists('associations');
    }
};
