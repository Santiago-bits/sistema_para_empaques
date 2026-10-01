<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operación: ubicaciones, pallets, cajones, producción, calidad, rechazos,
 * paradas, movimientos, historial de estados y auditoría.
 *
 * La columna `version` en pallets/cajones/cargas implementa bloqueo optimista:
 * toda actualización crítica se hace con WHERE version = ? y se incrementa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('packer_id')->references('id')->on('packers')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('owners')->nullOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });

        // Permisos individuales por usuario: granted=true concede, false revoca lo que da el rol.
        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('granted')->default(true);
            $table->primary(['permission_id', 'user_id']);
        });

        Schema::create('user_warehouse', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'warehouse_id']);
        });

        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('warehouse_locations')->restrictOnDelete();
            // sector | aisle | rack | cold_room | zone | position | dispatch
            $table->string('type', 20)->index();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->unsignedInteger('capacity_pallets')->default(0);
            // Coordenadas para el mapa del galpón (grilla).
            $table->unsignedSmallInteger('map_x')->nullable();
            $table->unsignedSmallInteger('map_y')->nullable();
            $table->unsignedSmallInteger('map_w')->nullable();
            $table->unsignedSmallInteger('map_h')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('pallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('barcode', 60)->nullable()->unique();
            $table->foreignId('lot_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('producer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('variety_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('origin')->nullable();
            $table->dateTime('received_at')->index();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('gross_weight', 10, 2)->nullable();
            // empty | received | with_product | reserved | loaded | dispatched | voided
            $table->string('status', 20)->default('received')->index();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('barcode', 60)->nullable()->unique();
            $table->foreignId('pallet_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('producer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('variety_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('size_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('packer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_line_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight', 8, 2)->nullable();
            // Ver App\Enums\CrateStatus
            $table->string('status', 20)->default('registered');
            // pending | approved | rejected
            $table->string('quality_status', 20)->default('pending');
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            // Carga activa: la asignación atómica se hace con UPDATE ... WHERE current_load_id IS NULL.
            $table->unsignedBigInteger('current_load_id')->nullable()->index();
            $table->dateTime('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'variety_id', 'size_id']);
            $table->index(['packer_id', 'processed_at']);
            $table->index('processed_at');
            $table->index('created_at');
            $table->index('quality_status');
        });

        Schema::create('production_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('crate_id')->constrained()->restrictOnDelete();
            $table->foreignId('packer_id')->constrained()->restrictOnDelete();
            $table->foreignId('variety_id')->constrained()->restrictOnDelete();
            $table->foreignId('size_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_line_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight', 8, 2);
            $table->string('weight_source', 10)->default('manual'); // manual | scale
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('recorded_at')->index();
            // Clave idempotente enviada por el cliente: un reintento no genera un segundo registro.
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('authorization_reason')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['packer_id', 'recorded_at']);
            $table->index(['variety_id', 'recorded_at']);
            $table->index(['size_id', 'recorded_at']);
        });

        Schema::create('quality_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crate_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('pallet_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('result', 20)->index(); // approved | rejected | observed
            $table->string('grade', 20)->nullable();
            $table->string('caliber', 20)->nullable();
            $table->string('ripeness', 30)->nullable();
            $table->decimal('damage_pct', 5, 2)->default(0);
            $table->decimal('bruise_pct', 5, 2)->default(0);
            $table->decimal('rot_pct', 5, 2)->default(0);
            $table->decimal('reject_pct', 5, 2)->default(0);
            $table->text('defects')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('controlled_at')->index();
            $table->timestamps();
        });

        Schema::create('rejects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crate_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('variety_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('size_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('packer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reason_id')->constrained('reasons')->restrictOnDelete();
            $table->foreignId('quality_control_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight', 8, 2);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('rejected_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_stoppages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reason_id')->constrained('reasons')->restrictOnDelete();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('location_movements', function (Blueprint $table) {
            $table->id();
            $table->morphs('movable'); // pallet | crate
            $table->foreignId('from_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('to_label')->nullable(); // p.ej. "Camión AB123CD"
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->dateTime('moved_at')->index();
        });

        Schema::create('state_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('stateful');
            $table->string('from_state', 30)->nullable();
            $table->string('to_state', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->dateTime('created_at')->index();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40)->index();
            $table->nullableMorphs('auditable');
            $table->string('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->dateTime('created_at')->index();
        });

        Schema::create('system_errors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('category', 30)->default('exception')->index(); // exception | api | arca | print | connection
            $table->text('message');
            $table->string('exception')->nullable();
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->longText('trace')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('created_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['system_errors', 'audit_logs', 'state_histories', 'location_movements', 'production_stoppages',
            'rejects', 'quality_controls', 'production_records', 'crates', 'pallets', 'warehouse_locations',
            'user_warehouse', 'permission_user'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['packer_id']);
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['client_id']);
        });
    }
};
