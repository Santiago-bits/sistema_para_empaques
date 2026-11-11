<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Datos del listado de movimientos de ARCA / SENASA para cada DTV-e: fecha de carga, vencimiento, estado
 * (Emitido / Cerrado / Anulado) y si requiere intervención. Sólo para control: el movimiento se hace en ARCA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtv_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('dtv_documents', 'loaded_on')) {
                $table->date('loaded_on')->nullable()->after('date');
            }
            if (! Schema::hasColumn('dtv_documents', 'expires_on')) {
                $table->date('expires_on')->nullable()->after('loaded_on');
            }
            if (! Schema::hasColumn('dtv_documents', 'arca_status')) {
                $table->string('arca_status', 20)->nullable()->after('expires_on');
            }
            if (! Schema::hasColumn('dtv_documents', 'needs_intervention')) {
                $table->boolean('needs_intervention')->default(false)->after('arca_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dtv_documents', function (Blueprint $table) {
            foreach (['loaded_on', 'expires_on', 'arca_status', 'needs_intervention'] as $column) {
                if (Schema::hasColumn('dtv_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
