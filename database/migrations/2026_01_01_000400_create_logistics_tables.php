<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logística y documentación: cargas, cajones por carga, checklist de despacho,
 * remitos, documentos adjuntos, facturas y registros de ARCA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->date('date')->index();
            $table->foreignId('truck_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('transporter_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained()->restrictOnDelete();
            // draft | closed | dispatched | delivered | cancelled
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('planned_crates')->nullable();
            $table->unsignedInteger('total_crates')->default(0);
            $table->decimal('total_kg', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dispatched_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('crates', function (Blueprint $table) {
            $table->foreign('current_load_id')->references('id')->on('loads')->nullOnDelete();
        });

        Schema::create('load_crates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_id')->constrained()->restrictOnDelete();
            $table->foreignId('crate_id')->constrained()->restrictOnDelete();
            // Igual a crate_id mientras la asignación está vigente, NULL al quitarla:
            // el índice único garantiza que un cajón esté en una sola carga activa.
            $table->unsignedBigInteger('active_crate_id')->nullable()->unique();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('added_at');
            $table->dateTime('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['load_id', 'removed_at']);
        });

        Schema::create('dispatch_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_id')->constrained()->cascadeOnDelete();
            $table->string('item', 30);
            $table->boolean('checked')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('checked_at')->nullable();
            $table->string('notes')->nullable();
            $table->unique(['load_id', 'item']);
        });

        Schema::create('remitos', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('load_id')->constrained()->restrictOnDelete();
            // = load_id mientras el remito no está anulado; NULL al anularlo. El índice único
            // garantiza un solo remito vigente por carga y permite reemitir tras una anulación.
            $table->unsignedBigInteger('active_load_id')->nullable()->unique();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('truck_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('issued_at')->index();
            $table->unsignedInteger('total_crates');
            $table->decimal('total_kg', 12, 2);
            // issued | delivered | voided
            $table->string('status', 20)->default('issued')->index();
            // Token aleatorio para la consulta pública por QR (no expone IDs).
            $table->string('public_token', 64)->unique();
            $table->text('notes')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_dni', 12)->nullable();
            $table->string('signature_path')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('remito_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remito_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variety_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('size_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('crates');
            $table->decimal('kg', 12, 2);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->string('type', 30)->index(); // remito | invoice | order | certificate | transport | receipt | other
            $table->string('title');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('remito_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('load_id')->nullable()->constrained()->nullOnDelete();
            // Código de comprobante ARCA: 1=Factura A, 6=Factura B, 11=Factura C, 3/8/13 notas de crédito.
            $table->unsignedSmallInteger('voucher_type');
            $table->unsignedInteger('point_of_sale');
            // El número definitivo lo asigna la autorización (CAE); en borrador es NULL.
            $table->unsignedBigInteger('number')->nullable();
            $table->date('issued_on')->index();
            $table->string('currency', 3)->default('ARS');
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            // draft | pending | authorized | rejected | voided
            $table->string('status', 20)->default('draft')->index();
            $table->string('cae', 20)->nullable();
            $table->date('cae_expires_on')->nullable();
            $table->string('arca_mode', 15)->nullable(); // simulation | homologation | production
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['point_of_sale', 'voucher_type', 'number', 'arca_mode']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 10)->default('kg');
            $table->decimal('unit_price', 14, 4);
            $table->decimal('vat_rate', 5, 2)->default(21);
            $table->decimal('subtotal', 14, 2);
        });

        Schema::create('arca_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 15);
            $table->string('operation', 40); // FECAESolicitar, FECompUltimoAutorizado, ...
            $table->string('status', 20); // success | error
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('created_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['arca_records', 'invoice_items', 'invoices', 'documents', 'remito_items', 'remitos',
            'dispatch_checks', 'load_crates'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('crates', fn (Blueprint $table) => $table->dropForeign(['current_load_id']));
        Schema::dropIfExists('loads');
    }
};
