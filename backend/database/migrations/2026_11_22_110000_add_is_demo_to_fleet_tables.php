<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Markierung für erfundene Demo-Datensätze (wie bei den Kunden): Entfernen trifft nur diese. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['vehicles', 'drivers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('is_demo')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['vehicles', 'drivers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['is_demo']);
                $t->dropColumn('is_demo');
            });
        }
    }
};
