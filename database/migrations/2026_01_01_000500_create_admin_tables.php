<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administración: insumos, mantenimiento, cámaras frigoríficas, incidentes,
 * alertas, cierres diarios, backups, soporte, licencias, importaciones y costos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('category', 40)->nullable(); // cajas, etiquetas, film, ...
            $table->string('unit', 10)->default('u');
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->constrained()->restrictOnDelete();
            $table->string('type', 10); // in | out | adjust
            $table->decimal('quantity', 12, 2);
            $table->decimal('stock_after', 12, 2);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('moved_at')->index();
            $table->timestamps();
        });

        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('brand', 60)->nullable();
            $table->string('model', 60)->nullable();
            $table->string('serial_number', 80)->nullable();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('status', 20)->default('operational'); // operational | maintenance | out_of_service
            $table->date('next_maintenance_on')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();
            $table->string('type', 20); // preventive | corrective | emergency
            $table->date('date')->index();
            $table->string('technician')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->text('parts_used')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cold_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->decimal('temp_min', 5, 2)->default(0);
            $table->decimal('temp_max', 5, 2)->default(8);
            $table->decimal('humidity_min', 5, 2)->nullable();
            $table->decimal('humidity_max', 5, 2)->nullable();
            // Identificador del sensor para integraciones futuras vía API.
            $table->string('sensor_key', 64)->nullable()->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('temperature_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cold_room_id')->constrained()->cascadeOnDelete();
            $table->decimal('temperature', 5, 2);
            $table->decimal('humidity', 5, 2)->nullable();
            $table->string('source', 10)->default('manual'); // manual | sensor
            $table->boolean('out_of_range')->default(false)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('recorded_at')->index();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('type', 40)->index();
            $table->dateTime('occurred_at')->index();
            $table->string('area', 40)->nullable();
            $table->text('description');
            $table->string('priority', 10)->default('medium'); // low | medium | high | critical
            $table->string('status', 20)->default('open')->index(); // open | in_progress | resolved | closed
            $table->text('resolution')->nullable();
            $table->nullableMorphs('related');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index(); // stock_low | temperature | load_pending | document_expiring | ...
            $table->string('severity', 10)->default('warning'); // info | warning | critical
            $table->string('title');
            $table->text('message')->nullable();
            $table->nullableMorphs('alertable');
            // Huella para no duplicar la misma alerta mientras siga abierta.
            $table->string('fingerprint', 120)->nullable()->unique();
            $table->dateTime('resolved_at')->nullable()->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->json('snapshot');
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('closed_at');
            $table->dateTime('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reopen_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['warehouse_id', 'date']);
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('disk', 20)->default('local');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('type', 10)->default('manual'); // manual | daily | weekly
            $table->string('status', 15)->default('running'); // running | success | failed
            $table->string('checksum', 64)->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('priority', 10)->default('medium');
            $table->string('status', 20)->default('open')->index(); // open | review | development | resolved | closed
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->boolean('from_developer')->default(false);
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('installation_id', 64)->unique();
            $table->string('client_name');
            $table->string('license_key', 128)->unique();
            $table->string('plan', 30)->default('standard');
            $table->date('starts_on');
            $table->date('expires_on')->nullable();
            $table->string('status', 15)->default('active'); // active | suspended | expired
            $table->json('modules')->nullable();
            $table->string('version', 20)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // clients | producers | varieties | sizes | crates
            $table->string('filename');
            $table->string('path');
            $table->string('status', 15)->default('validated'); // validated | imported | failed | discarded
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->json('errors')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('costs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30)->index(); // supplies | transport | labor | maintenance | other
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('ARS');
            $table->date('date')->index();
            $table->nullableMorphs('costable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['costs', 'import_batches', 'licenses', 'support_ticket_replies', 'support_tickets', 'backups',
            'daily_closings', 'notifications', 'alerts', 'incidents', 'temperature_records', 'cold_rooms',
            'maintenances', 'machines', 'inventory_movements', 'supplies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
