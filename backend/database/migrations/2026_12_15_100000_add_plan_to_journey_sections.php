<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * journey_sections.plan: alles, was die Streckenplanung braucht, um eine Strecke der Reise wieder zu öffnen (Betriebshof, Halte mit
 * Verweis auf den Ort, berechnetes Ergebnis mit Kartenlinie, berechnete Kennzahlen). Getrennt von tour_data, damit die große Linie nie
 * in die Fahrtbeschreibung eines Belegs gelangt. Ältere Strecken haben keinen Plan (null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journey_sections', function (Blueprint $table) {
            $table->longText('plan')->nullable()->after('tour_data');
        });
    }

    public function down(): void
    {
        Schema::table('journey_sections', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }
};
