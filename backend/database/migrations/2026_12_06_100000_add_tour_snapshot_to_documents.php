<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Die Fahrtbeschreibung (Strecken, Halte, Kilometer, Zeiten, Fahrzeuge), wie sie beim Festschreiben galt. Eine spätere Änderung
        // am Tour-Archiv ändert den Beleg nicht (GoBD). Bei Entwürfen und älteren Belegen leer.
        Schema::table('documents', function (Blueprint $table) {
            $table->json('tour_snapshot')->nullable()->after('tax_summary');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('tour_snapshot');
        });
    }
};
