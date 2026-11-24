<?php

use App\Models\NumberRange;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fahrtauftrag: je Strecke und Bus einer. Er entsteht als Entwurf mit der Auftragsbestätigung (Fahrzeugtyp vorbelegt),
        // der Disponent weist später Fahrzeug und Fahrer zu. Die Nummer kommt erst beim Ausgeben (wie bei den Belegen).
        Schema::create('trip_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 30)->nullable()->unique();
            $table->string('status', 20)->default('entwurf')->index();   // entwurf, disponiert, ausgegeben, erledigt

            // Herkunft: Abschnitt der Reise und Auftragsbestätigung; beide leer bei einem Auftrag ohne Reise (Werksverkehr, später)
            $table->foreignUuid('journey_section_id')->nullable()->constrained('journey_sections')->restrictOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->restrictOnDelete();

            $table->string('title', 150);                                // Strecke, wie sie in der Fahrtbeschreibung heißt
            $table->string('trip_kind', 20)->default('gelegenheit');
            $table->string('vehicle_type', 20)->nullable();              // Bedarf aus dem Angebot
            $table->unsignedSmallInteger('bus_no')->default(1);          // laufende Nummer des Busses innerhalb Strecke und Typ
            $table->json('route')->nullable();                           // Fahrtbeschreibung der Strecke aus dem Beleg-Stand (ohne Preise)

            // Disposition: eigenes Fahrzeug oder Fremdunternehmer / gestellter Bus
            $table->foreignUuid('vehicle_id')->nullable()->constrained('vehicles')->restrictOnDelete();
            $table->string('external_company', 150)->nullable();
            $table->string('external_plate', 30)->nullable();
            $table->foreignUuid('driver_id')->nullable()->constrained('drivers')->restrictOnDelete();
            $table->foreignUuid('driver2_id')->nullable()->constrained('drivers')->restrictOnDelete();

            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->dateTime('departure_at')->nullable();
            $table->unsignedSmallInteger('passengers')->nullable();
            $table->text('driver_note')->nullable();                     // Hinweise für den Fahrer (steht im Fahrer-PDF)
            $table->text('notes')->nullable();                           // intern

            // Für Etappe 3 und 4 angelegt: nichts wird doppelt oder gar nicht abgerechnet
            $table->boolean('collective_only')->default(false);          // „Nur Sammelrechnung“
            $table->string('billing_status', 20)->default('offen');      // offen, abgerechnet

            $table->dateTime('issued_at')->nullable();
            $table->uuid('issued_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            // Dieselbe Strecke, derselbe Typ und derselbe Bus nie doppelt: macht das Erzeugen wiederholbar
            $table->unique(['journey_section_id', 'vehicle_type', 'bus_no'], 'trip_orders_section_bus_unique');
        });

        // Neuer Nummernkreis für bestehende Firmen (neue Firmen bekommen ihn aus dem SettingsSeeder)
        if (NumberRange::query()->exists()) {
            NumberRange::firstOrCreate(['type' => 'fahrtauftrag'], [
                'label' => 'Fahrtauftrag', 'prefix' => 'FA', 'include_year' => true, 'padding' => 5, 'reset_yearly' => true, 'next_number' => 1,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_orders');
    }
};
