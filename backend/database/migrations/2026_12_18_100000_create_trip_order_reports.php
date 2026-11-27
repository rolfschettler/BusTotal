<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rückerfassung (Etappe 3): was der Fahrer auf dem Erfassungsblatt notiert hat. Zeiten und Kilometerstände, Grenzübertritte,
 * Zusatzleistungen und Kosten je Fahrtauftrag, dazu wer ihn wann auf „erledigt“ gesetzt hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_orders', function (Blueprint $table) {
            $table->string('actual_passengers', 40)->nullable();
            $table->text('report_notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->uuid('completed_by')->nullable();
            // Liste „Noch zu erfassen“: Stand und Fahrtende
            $table->index(['status', 'date_to']);
        });

        $rows = function (string $name, callable $columns): void {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->uuid('id')->primary();
                $table->foreignUuid('trip_order_id')->constrained('trip_orders')->cascadeOnDelete();
                $table->unsignedSmallInteger('position');
                $columns($table);
                $table->timestamps();
                $table->index(['trip_order_id', 'position']);
            });
        };

        $rows('trip_order_times', function (Blueprint $table) {
            $table->string('event', 100);
            $table->date('date')->nullable();
            $table->string('place', 150)->nullable();
            $table->string('time', 5)->nullable();
            $table->unsignedInteger('km')->nullable();
            // Art der Strecke BIS zu dieser Zeile (für den Auslandsanteil): betriebshof, befoerderung, leerfahrt
            $table->string('leg', 20)->nullable();
        });
        $rows('trip_order_borders', function (Blueprint $table) {
            $table->string('country', 60);
            $table->unsignedInteger('km_in')->nullable();
            $table->unsignedInteger('km_out')->nullable();
            $table->string('source', 20)->default('fahrer');   // fahrer, tachograph, navigation
        });
        $rows('trip_order_extras', function (Blueprint $table) {
            $table->string('title', 150);
            $table->string('quantity', 40)->nullable();
            $table->string('place', 100)->nullable();
            $table->decimal('amount', 10, 2)->nullable();      // brutto
            $table->string('payment', 10)->default('rechnung'); // bar, rechnung
            $table->decimal('tax_rate', 5, 2)->default(0);
        });
        $rows('trip_order_costs', function (Blueprint $table) {
            $table->string('category', 20);
            $table->decimal('amount', 10, 2)->nullable();      // brutto
            $table->string('receipt_no', 60)->nullable();
            $table->boolean('travel_service')->default(false); // Reisevorleistung (§ 25)
            $table->boolean('charge_customer')->default(false);
        });
    }

    public function down(): void
    {
        foreach (['trip_order_costs', 'trip_order_extras', 'trip_order_borders', 'trip_order_times'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('trip_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'date_to']);
            $table->dropColumn(['actual_passengers', 'report_notes', 'completed_at', 'completed_by']);
        });
    }
};
