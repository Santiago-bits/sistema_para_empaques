<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Movimientos de envases (bines, jaulas, cajas) con clientes, productores, transportistas, proveedores,
 * empleados y camioneros: quién se llevó o devolvió cuántos, y si fue como parte de pago (en ese caso
 * queda enlazado al movimiento de su cuenta corriente). Nunca se edita: se anula con motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('container_movements')) {
            return;
        }

        Schema::create('container_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('holder_type', 30);
            $table->unsignedBigInteger('holder_id');
            $table->foreignId('container_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('direction', 10); // delivered = se los entregamos | received = nos los entregó
            $table->unsignedInteger('quantity');
            $table->string('reason', 20)->default('loan'); // loan = préstamo o devolución | payment = como parte de pago
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->foreignId('account_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['holder_type', 'holder_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_movements');
    }
};
