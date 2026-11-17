<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Kennzeichnet Demo-Datensätze (Schulung, Messe, eigene Tests). Nur so lassen sie sich
            // gezielt wieder entfernen, ohne echte Kunden zu berühren.
            $table->boolean('is_demo')->default(false)->index()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
