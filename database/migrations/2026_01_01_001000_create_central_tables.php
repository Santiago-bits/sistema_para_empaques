<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel General (servidor central del proveedor) y sincronización de cada empaque con él.
 * En el central: clientes (licencias) con datos de contacto, reportes de uso y pedidos de soporte.
 * En cada empaque: marcas de sincronización de sus tickets y respuestas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('contact_name', 120)->nullable()->after('client_name');
            $table->string('contact_phone', 50)->nullable()->after('contact_name');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->string('locality', 120)->nullable()->after('contact_email');
        });

        Schema::create('usage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->dateTime('reported_at');
            $table->string('version', 20)->nullable();
            $table->json('metrics');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['license_id', 'reported_at']);
        });

        Schema::create('client_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('remote_number', 30);
            $table->string('subject');
            $table->text('description');
            $table->string('priority', 10)->default('medium');
            $table->string('status', 20)->default('open')->index();
            $table->string('requester', 160)->nullable();
            $table->dateTime('created_remote_at')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->boolean('status_pending')->default(false); // cambio de estado aún no entregado al empaque
            $table->timestamps();
            $table->unique(['license_id', 'remote_number']);
        });

        Schema::create('client_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author', 160);
            $table->text('body');
            $table->boolean('from_developer')->default(false);
            $table->unsignedBigInteger('remote_reply_id')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['client_ticket_id', 'remote_reply_id']);
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dateTime('central_synced_at')->nullable();
        });

        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->unsignedBigInteger('central_id')->nullable()->unique();
            $table->dateTime('central_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->dropUnique(['central_id']);
            $table->dropColumn(['central_id', 'central_synced_at']);
        });
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn('central_synced_at');
        });
        Schema::dropIfExists('client_ticket_messages');
        Schema::dropIfExists('client_tickets');
        Schema::dropIfExists('usage_reports');
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_phone', 'contact_email', 'locality']);
        });
    }
};
