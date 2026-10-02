<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos completos de choferes, camiones, transportistas, proveedores, embaladores, productores y empleados
 * (los que se piden todos los días) e importación que también actualiza registros existentes.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'drivers' => ['cuil', 'email', 'address', 'locality', 'birth_date', 'license_category', 'emergency_contact', 'emergency_phone', 'notes'],
        'trucks' => ['year', 'chassis_number', 'insurance_company', 'insurance_policy', 'insurance_expires_on', 'vtv_expires_on', 'senasa_expires_on', 'notes'],
        'transporters' => ['address', 'locality', 'province', 'tax_condition', 'cbu', 'bank_alias', 'notes'],
        'providers' => ['locality', 'province', 'tax_condition', 'cbu', 'bank_alias', 'notes'],
        'packers' => ['cuil', 'phone', 'address', 'birth_date'],
        'producers' => ['renspa', 'tax_condition', 'cbu', 'bank_alias'],
        'employees' => ['address', 'birth_date', 'cbu', 'bank_alias'],
        'clients' => ['notes'],
    ];

    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('mode', 10)->default('create')->after('type'); // create | upsert
            $table->unsignedInteger('updated_rows')->default(0)->after('valid_rows');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('cuil', 11)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('locality', 120)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('license_category', 20)->nullable();
            $table->string('emergency_contact', 120)->nullable();
            $table->string('emergency_phone', 50)->nullable();
            $table->text('notes')->nullable();
        });

        Schema::table('trucks', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('chassis_number', 40)->nullable();
            $table->string('insurance_company', 120)->nullable();
            $table->string('insurance_policy', 60)->nullable();
            $table->date('insurance_expires_on')->nullable();
            $table->date('vtv_expires_on')->nullable();
            $table->date('senasa_expires_on')->nullable();
            $table->text('notes')->nullable();
        });

        foreach (['transporters', 'providers'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if ($name === 'transporters') {
                    $table->string('address')->nullable();
                }
                $table->string('locality', 120)->nullable();
                $table->string('province', 60)->nullable();
                $table->string('tax_condition', 5)->nullable();
                $table->string('cbu', 22)->nullable();
                $table->string('bank_alias', 60)->nullable();
                $table->text('notes')->nullable();
            });
        }

        Schema::table('packers', function (Blueprint $table) {
            $table->string('cuil', 11)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->date('birth_date')->nullable();
        });

        Schema::table('producers', function (Blueprint $table) {
            $table->string('renspa', 30)->nullable();
            $table->string('tax_condition', 5)->nullable();
            $table->string('cbu', 22)->nullable();
            $table->string('bank_alias', 60)->nullable();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('cbu', 22)->nullable();
            $table->string('bank_alias', 60)->nullable();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($columns));
        }
        Schema::table('import_batches', fn (Blueprint $t) => $t->dropColumn(['mode', 'updated_rows']));
    }
};
