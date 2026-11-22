<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Archiv der E-Rechnung (XRechnung-XML) eines festgeschriebenen Belegs, wie beim Archiv-PDF: Die Datei entsteht einmal beim
        // Festschreiben, liegt im privaten Speicher und wird nie neu erzeugt. Pfad und Prüfsumme sind nach dem Festschreiben
        // einmal setzbar (Document::MUTABLE_AFTER_FINALIZE), danach nie wieder.
        Schema::table('documents', function (Blueprint $table) {
            $table->string('einvoice_path')->nullable()->after('archived_at');
            $table->char('einvoice_sha256', 64)->nullable()->after('einvoice_path');
            $table->string('einvoice_format', 20)->nullable()->after('einvoice_sha256');   // xrechnung
            $table->timestamp('einvoice_at')->nullable()->after('einvoice_format');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['einvoice_path', 'einvoice_sha256', 'einvoice_format', 'einvoice_at']);
        });
    }
};
