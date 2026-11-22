<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fahrtart der Position (gelegenheit, linie, schule, sonstig). Leer bei Positionen, die keine Fahrt sind (und bei alten Belegen).
        Schema::table('document_lines', function (Blueprint $table) {
            $table->string('trip_kind', 20)->nullable()->after('unit');
        });

        // Steuersatz je Fahrtart. Der bisherige Satz (tax_rate) gilt künftig für Gelegenheitsverkehr und Sonstiges.
        Schema::table('pricing_settings', function (Blueprint $table) {
            $table->decimal('tax_rate_line', 5, 2)->default(7)->after('tax_rate');
            $table->decimal('tax_rate_school', 5, 2)->default(7)->after('tax_rate_line');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_settings', function (Blueprint $table) {
            $table->dropColumn(['tax_rate_line', 'tax_rate_school']);
        });
        Schema::table('document_lines', function (Blueprint $table) {
            $table->dropColumn('trip_kind');
        });
    }
};
