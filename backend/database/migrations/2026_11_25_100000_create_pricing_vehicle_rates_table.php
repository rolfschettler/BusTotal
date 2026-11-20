<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preise je Fahrzeugtyp: höchstens eine Zeile je Typ. Ein leeres Feld (NULL) heißt: Es gilt der Standardpreis
        // aus pricing_settings. Leerfahrt-Prozent und Steuersatz bleiben bewusst überall gleich.
        Schema::create('pricing_vehicle_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vehicle_type', 20)->unique();
            $table->decimal('price_per_km', 8, 2)->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->decimal('day_rate', 10, 2)->nullable();
            $table->decimal('overnight_rate', 8, 2)->nullable();
            $table->decimal('minimum_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_vehicle_rates');
    }
};
