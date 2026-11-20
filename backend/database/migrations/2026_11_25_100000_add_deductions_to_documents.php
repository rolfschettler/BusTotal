<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schlussrechnung: erhaltene Anzahlungen werden NICHT als Positionen abgezogen, sondern getrennt ausgewiesen
        // (§ 14 Abs. 5 UStG: Gesamtentgelt, vereinnahmte Anzahlungen mit Steuer, Restbetrag).
        Schema::table('documents', function (Blueprint $table) {
            $table->json('deductions')->nullable()->after('tax_summary');   // [{document_id, number, document_date, net, tax, gross}]
            $table->decimal('deduction_net', 12, 2)->default(0)->after('deductions');
            $table->decimal('deduction_tax', 12, 2)->default(0)->after('deduction_net');
            $table->decimal('deduction_gross', 12, 2)->default(0)->after('deduction_tax');
            $table->decimal('payable_total', 12, 2)->default(0)->after('deduction_gross'); // Brutto minus Anzahlungen
        });

        // Bestehende Belege: Zahlbetrag = Brutto.
        DB::table('documents')->update(['payable_total' => DB::raw('gross_total')]);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['deductions', 'deduction_net', 'deduction_tax', 'deduction_gross', 'payable_total']);
        });
    }
};
