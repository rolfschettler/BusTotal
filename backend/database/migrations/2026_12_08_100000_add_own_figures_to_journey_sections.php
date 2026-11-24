<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strecken ohne Tour aus dem Archiv, zum Beispiel eine Fahrt vor Ort: Kilometer, Fahrzeit und Tage stehen direkt an der Strecke.
 * Bei Strecken mit Tour bleiben die Felder leer (die Zahlen kommen aus der Tour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journey_sections', function (Blueprint $table) {
            $table->decimal('loaded_km', 8, 1)->nullable()->after('trip_id');
            $table->unsignedInteger('drive_s')->nullable()->after('loaded_km');
            $table->unsignedSmallInteger('days')->nullable()->after('drive_s');
        });
    }

    public function down(): void
    {
        Schema::table('journey_sections', function (Blueprint $table) {
            $table->dropColumn(['loaded_km', 'drive_s', 'days']);
        });
    }
};
