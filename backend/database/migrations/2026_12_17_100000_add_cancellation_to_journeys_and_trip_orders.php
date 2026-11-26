<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reise absagen: Datum, Grund und wer es war an der Reise; die Fahrtaufträge bekommen den Zeitpunkt der Absage. Eine eigene Spalte
 * statt eines neuen Stands, damit „Absage zurücknehmen“ den vorherigen Stand (Entwurf, disponiert, ausgegeben) behält.
 * Der Index trägt die Ansichten der Reisenliste (Laufende, Abgeschlossen, Abgesagt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journeys', function (Blueprint $table) {
            $table->date('cancelled_on')->nullable()->after('date_to');
            $table->string('cancel_reason', 255)->nullable()->after('cancelled_on');
            $table->uuid('cancelled_by')->nullable()->after('cancel_reason');
            $table->index(['cancelled_on', 'date_to']);
        });
        Schema::table('trip_orders', function (Blueprint $table) {
            $table->dateTime('cancelled_at')->nullable()->after('issued_by')->index();
        });
    }

    public function down(): void
    {
        Schema::table('journeys', function (Blueprint $table) {
            $table->dropIndex(['cancelled_on', 'date_to']);
            $table->dropColumn(['cancelled_on', 'cancel_reason', 'cancelled_by']);
        });
        Schema::table('trip_orders', function (Blueprint $table) {
            $table->dropIndex(['cancelled_at']);
            $table->dropColumn('cancelled_at');
        });
    }
};
