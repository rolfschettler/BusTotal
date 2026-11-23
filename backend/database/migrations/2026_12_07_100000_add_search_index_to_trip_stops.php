<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suche im Tour-Archiv nach Orten: am Anfang des Ortsnamens (LIKE 'text%'), dafür der Index (siehe CLAUDE.md, Suche).
        Schema::table('trip_stops', function (Blueprint $table) {
            $table->index('label');
        });
    }

    public function down(): void
    {
        Schema::table('trip_stops', function (Blueprint $table) {
            $table->dropIndex(['label']);
        });
    }
};
