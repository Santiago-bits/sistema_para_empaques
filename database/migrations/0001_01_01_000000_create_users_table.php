<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            // El login no depende del email: puede usarse usuario, DNI, CUIT o código interno.
            $table->string('username', 60)->unique();
            $table->string('dni', 12)->nullable()->unique();
            $table->string('cuit', 13)->nullable()->unique();
            $table->string('internal_code', 30)->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 50)->nullable();
            // FK a roles/packers/owners/clients se agregan en migraciones posteriores.
            $table->unsignedBigInteger('role_id')->nullable()->index();
            $table->unsignedBigInteger('packer_id')->nullable()->index();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('status', 20)->default('active')->index(); // active | inactive | suspended
            $table->string('theme', 10)->default('system'); // light | dark | system
            $table->boolean('kiosk_mode')->default(false);
            $table->dateTime('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->dateTime('deactivated_at')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->dateTime('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
