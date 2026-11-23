<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tour-Archiv: eine geplante Strecke zum Wiederverwenden. Die Halte verweisen auf gespeicherte Orte (places),
        // die berechnete Strecke (Zeitplan, Linie) wird mitgespeichert, damit nichts neu gerechnet werden muss.
        Schema::create('trips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 150)->index();
            $table->string('trip_kind', 20)->default('gelegenheit');     // bestimmt den Steuersatz (TripKind)
            // Fahrzeugbedarf: ein Typ und eine Anzahl, kein konkretes Fahrzeug (das wählt der Disponent später)
            $table->string('vehicle_type', 20)->nullable();
            $table->unsignedSmallInteger('vehicle_count')->default(1);
            $table->foreignUuid('depot_id')->nullable()->constrained('depots')->nullOnDelete();
            $table->boolean('return_to_depot')->default(false);
            $table->boolean('round_trip')->default(false);               // Rückfahrt zum Startort

            // Kennzahlen der Berechnung
            $table->decimal('loaded_km', 9, 1)->default(0);
            $table->decimal('empty_km', 9, 1)->default(0);
            $table->unsignedInteger('drive_s')->default(0);              // reine Lenkzeit
            $table->unsignedInteger('elapsed_s')->default(0);            // Gesamtdauer mit Pausen und Ruhezeiten
            $table->unsignedSmallInteger('days')->default(1);

            $table->json('route')->nullable();                           // Teilstrecken, Zeitplan, Zusammenfassung, Hinweise
            $table->longText('geometry')->nullable();                    // Linienzug [[lat, lon], …], nur im Einzelabruf
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_stops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('label');                                     // Name des Ortes, wie in der Planung angezeigt
            $table->foreignUuid('place_id')->constrained('places')->restrictOnDelete();
            $table->unsignedSmallInteger('dwell_minutes')->default(0);
            $table->boolean('driver_free')->default(false);
            $table->timestamps();

            $table->unique(['trip_id', 'position']);
        });

        // Reisevorhaben: optionaler, schlanker Container über Belege und Fahrtaufträge. Summen und Stand werden abgeleitet.
        Schema::create('journeys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 150)->index();
            $table->foreignUuid('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->timestamps();
        });

        Schema::create('journey_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journey_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('kind', 20);                                  // hinfahrt, vor_ort, rueckfahrt
            $table->string('title', 150)->nullable();
            $table->foreignUuid('trip_id')->nullable()->constrained('trips')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['journey_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_sections');
        Schema::dropIfExists('journeys');
        Schema::dropIfExists('trip_stops');
        Schema::dropIfExists('trips');
    }
};
