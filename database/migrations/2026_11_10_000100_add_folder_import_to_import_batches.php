<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Importación de una carpeta entera: cada planilla queda en su propio lote, agrupado por «group_uuid»,
 * con el resultado de la detección (a qué sección va y por qué) en «detection».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('import_batches', 'group_uuid')) {
            return;
        }

        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('group_uuid', 36)->nullable()->index()->after('mode');
            $table->json('detection')->nullable()->after('group_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropIndex(['group_uuid']);
            $table->dropColumn(['group_uuid', 'detection']);
        });
    }
};
