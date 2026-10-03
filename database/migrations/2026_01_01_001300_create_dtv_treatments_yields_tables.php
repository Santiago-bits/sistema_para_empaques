<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el galpón llevaba en Excel (planilla «LA CALANDRIA»):
 * - DTV-e (SENASA): registro de ingresos y egresos con sus líneas por especie/variedad y kilos.
 * - Tratamientos (p. ej. para entrar a la Patagonia): fecha, destino, cantidad, tipo y empresa que trata.
 * - Rendimiento de cera e insumos: de tal fecha a tal fecha, cuántos bultos rindió (tambor de cera, etc.).
 * - Ingresos de fruta (lotes): chofer, bines y n° de DTV. Cargas: precio por unidad (subtotal = cantidad × precio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('producer_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('bins')->nullable()->after('quantity');
            $table->string('dtv_number', 30)->nullable()->after('bins')->index();
        });

        Schema::table('loads', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->nullable()->after('freight_amount');
        });

        Schema::create('dtv_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date')->index();
            $table->string('direction', 3)->index(); // in = ingreso, out = egreso
            $table->string('number', 30)->index();
            $table->string('doc_type', 20)->nullable(); // EMP-CTC, etc.
            $table->string('issuer', 120)->nullable(); // emisor
            $table->string('establishment', 40)->nullable(); // E-2929-b-C
            $table->string('recipient', 160)->nullable(); // destinatario
            $table->string('destination', 160)->nullable(); // destino
            $table->string('transport', 160)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('load_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['direction', 'number']);
        });

        Schema::create('dtv_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dtv_document_id')->constrained()->cascadeOnDelete();
            $table->string('species', 60)->nullable();
            $table->foreignId('variety_id')->nullable()->constrained()->nullOnDelete();
            $table->string('variety_name', 80)->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 20)->default('Cajón');
            $table->decimal('kg_per_unit', 10, 2)->nullable();
            $table->decimal('kg_total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('destination', 120)->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 20)->default('Cajón');
            $table->string('type', 60)->index();
            $table->string('provider', 120)->nullable(); // empresa que hace el tratamiento
            $table->foreignId('load_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dtv_number', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supply_yields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120); // «Tambor de cera N° 3»
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->decimal('quantity_used', 12, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->unsignedInteger('packages_manual')->nullable(); // si se carga a mano en lugar de contar la producción
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_yields');
        Schema::dropIfExists('treatments');
        Schema::dropIfExists('dtv_lines');
        Schema::dropIfExists('dtv_documents');
        Schema::table('loads', fn (Blueprint $table) => $table->dropColumn('unit_price'));
        Schema::table('lots', function (Blueprint $table) {
            $table->dropIndex(['dtv_number']);
        });
        Schema::table('lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
            $table->dropColumn(['bins', 'dtv_number']);
        });
    }
};
