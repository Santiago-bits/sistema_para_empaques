<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Notas de crédito: ARCA exige informar el comprobante que se ajusta (CbtesAsoc).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('associated_invoice_id')->nullable()->after('load_id')->constrained('invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('associated_invoice_id');
        });
    }
};
