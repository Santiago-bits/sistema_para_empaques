<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tesorería (caja, cuentas corrientes, cheques, cotización del dólar), personal (cuadrillas y
 * empleados), selecciones y envases, datos comerciales de las cargas y precio de compra de los lotes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------- Catálogos

        // Selección / categoría comercial (Extra, Elegido, Comercial…): va en la etiqueta de la caja.
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Tipos de envase (bin, caja de cartón, jaula…).
        Schema::create('container_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('kind', 20)->default('box'); // box | bin | crate | other
            $table->decimal('tare_kg', 8, 2)->nullable();
            $table->decimal('capacity_kg', 8, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crews', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('kind', 20)->default('packing'); // harvest | packing | loading | other
            $table->string('leader', 120)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('dni', 15)->nullable()->unique();
            $table->string('cuil', 13)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('position', 80)->nullable();
            $table->foreignId('crew_id')->nullable()->constrained()->nullOnDelete();
            $table->date('hired_on')->nullable();
            $table->decimal('daily_wage', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('crates', function (Blueprint $table) {
            $table->foreignId('grade_id')->nullable()->after('size_id')->constrained()->nullOnDelete();
            $table->foreignId('container_type_id')->nullable()->after('grade_id')->constrained()->nullOnDelete();
        });

        // Compra de fruta al productor: kilos y precio para liquidar en su cuenta corriente.
        Schema::table('lots', function (Blueprint $table) {
            $table->foreignId('container_type_id')->nullable()->after('variety_id')->constrained()->nullOnDelete();
            $table->decimal('kg_received', 12, 2)->nullable()->after('quantity');
            $table->decimal('price_per_kg', 14, 4)->nullable()->after('kg_received');
            $table->dateTime('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Datos comerciales y de transporte del egreso.
        Schema::table('loads', function (Blueprint $table) {
            $table->string('trailer_plate', 12)->nullable()->after('truck_id');
            $table->string('guide_number', 40)->nullable()->after('trailer_plate');
            $table->string('commercial_destination', 20)->nullable(); // domestic | export | industry
            $table->string('sales_channel', 20)->nullable();          // market | supermarket | export | industry | direct
            $table->string('sale_condition', 20)->nullable();          // cash | account | consignment
            $table->decimal('freight_amount', 14, 2)->nullable();
            $table->dateTime('freight_posted_at')->nullable();
        });

        // ---------------------------------------------------------------- Cotización

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('currency', 3)->default('USD');
            $table->decimal('buy', 12, 4)->nullable();
            $table->decimal('sell', 12, 4);
            $table->string('source', 40)->nullable(); // BNA oficial, MEP, manual…
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['date', 'currency']);
        });

        // ---------------------------------------------------------------- Cuentas corrientes

        // Libro mayor de cuentas corrientes. Nunca se edita: se anula con motivo (y queda auditado).
        // debit = el titular nos debe más (factura, pago que le hicimos); credit = le debemos más (compra, cobro).
        Schema::create('account_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('holder_type', 30);
            $table->unsignedBigInteger('holder_id');
            $table->date('date');
            $table->string('type', 30); // ver App\Models\AccountMovement::TYPES
            $table->string('description');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('payment_method', 20)->nullable(); // cash | transfer | check | other
            $table->string('reference', 80)->nullable();
            $table->nullableMorphs('source');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['holder_type', 'holder_id', 'date']);
            $table->index('date');
            // Un mismo comprobante/lote/flete/cheque se imputa una sola vez por tipo y titular
            // (al anular un movimiento con origen, se libera el origen para poder volver a imputarlo).
            $table->unique(['source_type', 'source_id', 'type', 'holder_type', 'holder_id'], 'account_movements_source_unique');
        });

        // ---------------------------------------------------------------- Cheques

        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('kind', 15); // third_party | own
            $table->boolean('electronic')->default(false);
            $table->string('bank', 80);
            $table->string('number', 30);
            $table->string('issuer_name', 120)->nullable();
            $table->string('issuer_cuit', 13)->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('issued_on');
            $table->date('payment_date')->index();
            // third_party: in_portfolio | deposited | cashed | endorsed | rejected | voided
            // own: issued | paid | rejected | voided
            $table->string('status', 20)->index();
            $table->string('received_from_type', 30)->nullable();
            $table->unsignedBigInteger('received_from_id')->nullable();
            $table->string('delivered_to_type', 30)->nullable();
            $table->unsignedBigInteger('delivered_to_id')->nullable();
            $table->date('status_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kind', 'bank', 'number']);
        });

        // ---------------------------------------------------------------- Caja

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            // Igual a warehouse_id mientras está abierta, NULL al cerrar: una sola caja abierta por galpón.
            $table->unsignedBigInteger('open_warehouse_id')->nullable()->unique();
            $table->dateTime('opened_at');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('expected_balance', 14, 2)->nullable();
            $table->decimal('counted_balance', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();
            $table->dateTime('moved_at');
            $table->string('direction', 3); // in | out
            $table->string('category', 30); // ver App\Models\CashMovement::CATEGORIES
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->foreignId('account_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index(['cash_session_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('checks');
        Schema::dropIfExists('account_movements');
        Schema::dropIfExists('exchange_rates');

        Schema::table('loads', function (Blueprint $table) {
            $table->dropColumn(['trailer_plate', 'guide_number', 'commercial_destination', 'sales_channel', 'sale_condition', 'freight_amount', 'freight_posted_at']);
        });
        Schema::table('lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('container_type_id');
            $table->dropConstrainedForeignId('settled_by');
            $table->dropColumn(['kg_received', 'price_per_kg', 'settled_at']);
        });
        Schema::table('crates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
            $table->dropConstrainedForeignId('container_type_id');
        });

        Schema::dropIfExists('employees');
        Schema::dropIfExists('crews');
        Schema::dropIfExists('container_types');
        Schema::dropIfExists('grades');
    }
};
