<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Reise ist der Ort, an dem man Strecken Schritt für Schritt plant; das Angebot entsteht daraus.
 *
 *  - journey_sections.start_on / surcharge_percent: Fahrtdatum und Zuschlag der Strecke (für die Preise an Wochenende und Feiertag).
 *    Bisher steckten sie nur in der Anfrage und gingen verloren.
 *  - journey_sections.priced_signature: Hash der Eingaben, mit denen die Positionen der Strecke zuletzt berechnet wurden. Der Abgleich
 *    des Angebots erkennt daran, welche Strecke „geändert“ ist (null: unbekannt, gilt als aktuell, nie ungefragt überschreiben).
 *  - journeys.manual: Die Reise wurde von Hand angelegt („Neue Reise planen“). Eine solche Reise verschwindet nicht mit ihrem
 *    einzigen Entwurf (anders als die Reise, die mit dem Kurzweg „Angebot erstellen“ entstand).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journey_sections', function (Blueprint $table) {
            $table->date('start_on')->nullable()->after('days');
            $table->decimal('surcharge_percent', 5, 2)->default(0)->after('start_on');
            $table->string('priced_signature', 64)->nullable()->after('surcharge_percent');
        });

        Schema::table('journeys', function (Blueprint $table) {
            $table->boolean('manual')->default(false)->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('journeys', function (Blueprint $table) {
            $table->dropColumn('manual');
        });
        Schema::table('journey_sections', function (Blueprint $table) {
            $table->dropColumn(['start_on', 'surcharge_percent', 'priced_signature']);
        });
    }
};
