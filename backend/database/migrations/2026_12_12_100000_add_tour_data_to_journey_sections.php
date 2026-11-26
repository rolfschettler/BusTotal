<?php

use App\Models\JourneySection;
use App\Services\TourSnapshotService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Strecke einer Reise bekommt ihre eigene Kopie der Tour (`tour_data`), gemacht in dem Moment, in dem sie aus der Tour entsteht
 * (mit dem Angebot). Alles Weitere in der Belegkette (Reise, Beleg-Schnappschuss, Fahrtaufträge) arbeitet mit dieser Kopie.
 * Dadurch ist die Tour im Archiv nur noch Vorlage für künftige Angebote und lässt sich ändern und löschen: `trip_id` bleibt als
 * Hinweis „stammt aus Tour …“ und wird beim Löschen der Tour leer (statt das Löschen zu sperren).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journey_sections', function (Blueprint $table) {
            $table->json('tour_data')->nullable()->after('trip_id');
        });

        Schema::table('journey_sections', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
        });
        Schema::table('journey_sections', function (Blueprint $table) {
            $table->foreign('trip_id')->references('id')->on('trips')->nullOnDelete();
        });

        // Bestehende Strecken: die Kopie aus der Tour nachholen, wie sie heute im Archiv steht.
        $snapshots = app(TourSnapshotService::class);
        JourneySection::whereNotNull('trip_id')->with('trip.stops')->get()->each(function (JourneySection $section) use ($snapshots) {
            if ($section->trip) {
                $section->forceFill(['tour_data' => $snapshots->tripData($section->trip)])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('journey_sections', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
        });
        Schema::table('journey_sections', function (Blueprint $table) {
            $table->foreign('trip_id')->references('id')->on('trips')->restrictOnDelete();
            $table->dropColumn('tour_data');
        });
    }
};
