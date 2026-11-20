<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bundesland des Betriebssitzes (Kürzel, z. B. BY): bestimmt, welche Feiertage gelten. Leer = Feiertage werden nicht erkannt.
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('state', 2)->nullable()->after('country');
        });

        // Zuschläge in Prozent auf den Anteil des jeweiligen Tages. 0 = kein Zuschlag.
        Schema::table('pricing_settings', function (Blueprint $table) {
            $table->decimal('surcharge_saturday_percent', 5, 2)->default(0)->after('minimum_price');
            $table->decimal('surcharge_sunday_percent', 5, 2)->default(0)->after('surcharge_saturday_percent');
            $table->decimal('surcharge_holiday_percent', 5, 2)->default(0)->after('surcharge_sunday_percent');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_settings', function (Blueprint $table) {
            $table->dropColumn(['surcharge_saturday_percent', 'surcharge_sunday_percent', 'surcharge_holiday_percent']);
        });
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
