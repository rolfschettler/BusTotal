<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zu- und Ausstiege in einer Liste: "zustieg" (bisherige Zeilen) oder "ausstieg" (zum Beispiel bei der Rückreise)
        Schema::table('trip_order_boardings', function (Blueprint $table) {
            $table->string('kind', 10)->default('zustieg')->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('trip_order_boardings', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
