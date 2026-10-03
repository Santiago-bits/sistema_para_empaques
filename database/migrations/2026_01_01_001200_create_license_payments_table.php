<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administración general: cobro del sistema a cada empaque cliente (cuota mensual, pagos y hasta cuándo
 * está pagado). Los pagos nunca se borran: se anulan con motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->decimal('monthly_fee', 12, 2)->nullable()->after('plan');
            $table->string('fee_currency', 3)->default('ARS')->after('monthly_fee');
            $table->date('paid_until')->nullable()->after('fee_currency');
        });

        Schema::create('license_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('ARS');
            $table->date('paid_at');
            $table->unsignedSmallInteger('months')->default(1);
            $table->date('period_from');
            $table->date('period_to');
            $table->string('method', 20)->default('transfer');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index(['license_id', 'voided_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_payments');
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['monthly_fee', 'fee_currency', 'paid_until']);
        });
    }
};
