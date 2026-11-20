<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orte: DIE Stelle für Koordinaten. Jeder Datensatz mit Adresse, der in einer Geo-Funktion vorkommen kann (Betriebshof,
        // später Kundenadresse, Haltestelle, Reiseziel), verweist mit place_id hierher, statt eigene lat/lon-Spalten zu führen.
        // Eine Adresse wird nur einmal gesucht, auch wenn sie in mehreren Datensätzen steht (siehe App\Services\PlaceResolver).
        Schema::create('places', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('lookup_key', 40)->unique();      // sha1 des normalisierten Suchtextes (klein, ohne doppelte Leerzeichen)
            $table->string('query');                       // der Suchtext, wie er zuerst eingegeben wurde
            $table->string('label');                       // Name des gefundenen Ortes laut Dienst
            $table->decimal('lat', 10, 7);
            $table->decimal('lon', 10, 7);
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 10)->nullable();
            $table->string('source', 50);                  // welcher Dienst die Koordinaten geliefert hat (Herkunft, für Lizenz und Wechsel)
            $table->timestamp('geocoded_at');              // wann gesucht
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
