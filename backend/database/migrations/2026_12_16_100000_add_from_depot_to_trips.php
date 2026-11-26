<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** trips.from_depot: Beginnt die Tour mit der Anfahrt vom Betriebshof? Bisher immer, deshalb Vorgabe ja. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('from_depot')->default(true)->after('depot_id');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('from_depot');
        });
    }
};
