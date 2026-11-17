<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('personnel_number', 30)->unique();        // eigene Personalnummer, z. B. P-001
            $table->string('first_name');
            $table->string('last_name')->index();
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('employment_type', 20)->default('vollzeit')->index();
            $table->date('hired_on')->nullable();                    // Eintritt
            $table->decimal('hourly_rate', 8, 2)->nullable();        // Kostensatz je Stunde in Euro (Kalkulation)
            $table->json('license_classes')->nullable();             // Liste der Führerscheinklassen
            $table->string('license_number', 30)->nullable();
            $table->string('driver_card_number', 30)->nullable();    // Nummer der Fahrerkarte (digitaler Tacho)
            // Fristen: das Datum, bis zu dem das Dokument bzw. die Prüfung gilt
            $table->date('license_due_date')->nullable()->index();      // Führerschein
            $table->date('pbs_due_date')->nullable()->index();          // Führerschein zur Fahrgastbeförderung
            $table->date('driver_card_due_date')->nullable()->index();  // Fahrerkarte
            $table->date('code95_due_date')->nullable()->index();       // Berufskraftfahrer-Qualifikation (Code 95)
            $table->date('medical_due_date')->nullable()->index();      // ärztliche Untersuchung
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();                                   // Austritt: archivieren statt löschen
        });

        // Urlaub, Krankheit, Schulung: in dieser Zeit steht der Fahrer nicht zur Verfügung.
        Schema::create('driver_absences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                              // urlaub, krank, schulung, sonstige
            $table->date('start_date')->index();
            $table->date('end_date')->index();                       // einschließlich
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_absences');
        Schema::dropIfExists('drivers');
    }
};
