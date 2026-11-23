<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fahrzeugbedarf je Abschnitt: ein Abschnitt kann mehrere Fahrzeugtypen brauchen (zum Beispiel Doppeldecker und Kleinbus nach Wien).
        Schema::create('journey_section_vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journey_section_id')->constrained('journey_sections')->cascadeOnDelete();
            $table->string('vehicle_type', 20)->nullable();
            $table->unsignedSmallInteger('vehicle_count')->default(1);
            $table->timestamps();
        });

        // Jede Position weiß, zu welchem Abschnitt und Fahrzeugtyp sie gehört; so lassen sich die Positionen gruppieren.
        Schema::table('document_lines', function (Blueprint $table) {
            $table->foreignUuid('journey_section_id')->nullable()->after('document_id')->constrained('journey_sections')->restrictOnDelete();
            $table->string('vehicle_type', 20)->nullable()->after('journey_section_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journey_section_id');
            $table->dropColumn('vehicle_type');
        });
        Schema::dropIfExists('journey_section_vehicles');
    }
};
