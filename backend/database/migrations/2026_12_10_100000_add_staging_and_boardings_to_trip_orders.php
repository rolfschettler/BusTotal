<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_orders', function (Blueprint $table) {
            // Personenzahl als Text: „20“ ebenso wie „ca. 20 Pers.“
            $table->string('passengers', 40)->nullable()->change();
            // Bereitstellung: ab dieser Uhrzeit (am ersten Fahrtag) haben die Gäste Zugang zum Bus, "HH:MM"
            $table->string('staging_time', 5)->nullable()->after('departure_at');
        });

        // Zustiegsstellen: weitere Orte, an denen unterwegs Gäste zusteigen (Werks-, Vereinsfahrten mit Abholung). Sie werden von Hand
        // erfasst, unabhängig von der Streckenplanung. Nur Ort, Uhrzeit und, wenn bekannt, die Personenzahl dort.
        Schema::create('trip_order_boardings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trip_order_id')->constrained('trip_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('place', 150);
            $table->string('time', 5)->nullable();            // "HH:MM"
            $table->string('passengers', 40)->nullable();     // Text, z. B. "5" oder "ca. 5 Pers."
            $table->timestamps();

            $table->unique(['trip_order_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_order_boardings');
        Schema::table('trip_orders', function (Blueprint $table) {
            $table->dropColumn('staging_time');
        });
    }
};
