<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fahrtenschreiber-Daten auslesen (VO 561/2006, siehe docs/fachlich/lenk-ruhezeiten.md):
        // Fahrerkarte spätestens alle 28 Tage, Massenspeicher der Fahrzeugeinheit spätestens alle 90 Tage.
        // Gespeichert wird wie bei allen Fristen das Datum, bis zu dem ausgelesen sein muss.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('tachograph_read_due_date')->nullable()->index()->after('tachograph_due_date');
        });
        Schema::table('drivers', function (Blueprint $table) {
            $table->date('driver_card_read_due_date')->nullable()->index()->after('driver_card_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn('tachograph_read_due_date'));
        Schema::table('drivers', fn (Blueprint $table) => $table->dropColumn('driver_card_read_due_date'));
    }
};
