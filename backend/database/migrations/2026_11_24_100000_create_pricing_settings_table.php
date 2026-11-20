<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Genau eine Zeile je Mandanten-Datenbank (wie company_settings).
        // Alle Preise netto in Euro. 0 = Preisbestandteil ist ausgeschaltet.
        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('price_per_km', 8, 2)->default(0);        // Verkaufspreis je Kilometer
            $table->unsignedSmallInteger('empty_km_percent')->default(100); // Leerkilometer werden zu x % berechnet
            $table->decimal('hourly_rate', 8, 2)->default(0);         // Stundensatz auf die Lenkzeit
            $table->decimal('day_rate', 10, 2)->default(0);           // Tagespauschale je Einsatztag
            $table->decimal('overnight_rate', 8, 2)->default(0);      // Übernachtung des Fahrers je Nacht
            $table->decimal('minimum_price', 10, 2)->default(0);      // Mindestpreis netto je Angebot
            $table->decimal('tax_rate', 5, 2)->default(19);           // Vorbelegung des Steuersatzes der Positionen
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_settings');
    }
};
