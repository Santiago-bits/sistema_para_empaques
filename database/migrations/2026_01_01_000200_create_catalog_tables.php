<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos: productores, propietarios, clientes, destinos, proveedores,
 * transportistas, camiones, choferes, variedades, tamaños, turnos, líneas,
 * embaladores, motivos configurables y lotes.
 *
 * Productor, propietario, cliente y destino son entidades separadas a propósito:
 * quién produjo la fruta no es necesariamente quien la posee ni quien la recibe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('cuit', 13)->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('locality', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('cuit', 13)->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('name')->nullable();
            $table->string('cuit', 13)->nullable()->index();
            $table->string('dni', 12)->nullable();
            $table->string('address')->nullable();
            $table->string('locality', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            // Condición frente al IVA: RI, MT (monotributo), CF, EX
            $table->string('tax_condition', 4)->default('RI');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('locality', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cuit', 13)->nullable()->index();
            $table->string('contact')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('products')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transporters', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('cuit', 13)->nullable()->index();
            $table->string('contact')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 12)->unique();
            $table->string('brand', 60)->nullable();
            $table->string('model', 60)->nullable();
            $table->foreignId('transporter_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('capacity_kg', 10, 2)->nullable();
            $table->unsignedSmallInteger('capacity_pallets')->nullable();
            $table->string('type', 40)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('dni', 12)->unique();
            $table->string('license_number', 40)->nullable();
            $table->date('license_expires_on')->nullable()->index();
            $table->foreignId('transporter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 50)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('varieties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('species', 60)->nullable();
            $table->string('color', 9)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('production_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('production_targets', function (Blueprint $table) {
            $table->id();
            // daily | weekly | monthly
            $table->string('period', 10);
            $table->foreignId('production_line_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('target_kg', 12, 2)->default(0);
            $table->unsignedInteger('target_crates')->default(0);
            $table->timestamps();
            $table->unique(['period', 'production_line_id', 'shift_id']);
        });

        Schema::create('packers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('dni', 12)->nullable()->unique();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('hired_on')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Motivos configurables (rechazo, parada, incidente).
        Schema::create('reasons', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index(); // reject | stoppage | incident
            $table->string('code', 30);
            $table->string('name', 100);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['type', 'code']);
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->date('date')->index();
            $table->foreignId('producer_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('variety_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('origin')->nullable();
            $table->string('field')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('status', 20)->default('open')->index(); // open | closed | voided
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['lots', 'reasons', 'packers', 'production_targets', 'production_lines', 'shifts', 'sizes',
            'varieties', 'drivers', 'trucks', 'transporters', 'providers', 'destinations', 'clients',
            'owners', 'producers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
