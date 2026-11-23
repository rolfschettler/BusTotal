<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Das Reisevorhaben ist die sichtbare Klammer über der Belegkette (Angebot, Auftrag, Rechnung). Belege ohne Reise (von Hand
        // angelegt, Werksverkehr) haben keinen Verweis. Gesetzt wird er nur vom Server, solange der Beleg Entwurf ist.
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignUuid('journey_id')->nullable()->after('source_id')->constrained('journeys')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journey_id');
        });
    }
};
