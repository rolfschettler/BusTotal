<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('fleet_number', 30)->unique();             // eigene Fuhrparknummer, z. B. B-01
            $table->string('license_plate', 20)->unique();            // amtliches Kennzeichen, immer in Großbuchstaben
            $table->string('type', 20)->index();                      // reisebus, doppeldecker, midibus, minibus, sonstige
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->string('vin', 17)->nullable()->index();           // Fahrgestellnummer
            $table->unsignedSmallInteger('seats');                    // Sitzplätze (ohne Fahrer und Reiseleitung)
            $table->json('equipment')->nullable();                    // Liste der Ausstattungsschlüssel
            // Maße und Gewicht
            $table->decimal('length_m', 4, 2)->nullable();
            $table->decimal('width_m', 4, 2)->nullable();
            $table->decimal('height_m', 4, 2)->nullable();
            $table->unsignedInteger('gross_weight_kg')->nullable();   // zulässiges Gesamtgewicht
            $table->decimal('cost_per_km', 8, 2)->nullable();         // Kostensatz je km in Euro (Kalkulation)
            // Fristen: das Datum, bis zu dem die Prüfung fällig ist
            $table->date('hu_due_date')->nullable()->index();         // Hauptuntersuchung
            $table->date('sp_due_date')->nullable()->index();         // Sicherheitsprüfung
            $table->date('tachograph_due_date')->nullable()->index(); // Fahrtenschreiber
            $table->date('service_due_date')->nullable()->index();    // Wartung
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();                                    // Archivieren statt Löschen (spätere Belege verweisen darauf)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
