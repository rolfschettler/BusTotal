<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Archiv-PDF (GoBD): Das PDF eines festgeschriebenen Belegs entsteht einmal und wird unverändert aufbewahrt.
        // Die Datei liegt im privaten Speicher, hier stehen nur Pfad, SHA-256-Prüfsumme und Zeitpunkt.
        Schema::table('documents', function (Blueprint $table) {
            $table->string('archive_path')->nullable()->after('finalized_by');
            $table->char('archive_sha256', 64)->nullable()->after('archive_path');
            $table->timestamp('archived_at')->nullable()->after('archive_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['archive_path', 'archive_sha256', 'archived_at']);
        });
    }
};
