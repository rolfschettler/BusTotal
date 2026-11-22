<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zahlungsbuchungen zu Rechnungen und Anzahlungsrechnungen. Der Beleg selbst bleibt unverändert (GoBD), der Zahlstand
        // ergibt sich aus diesen Buchungen. Eine Buchung wird nie gelöscht, nur storniert (cancelled_at mit Grund).
        // Grundlage: Die Steuer auf Anzahlungen und Rückzahlungen hängt am tatsächlichen Zahlungsfluss (§ 13 UStG, UStAE 13.5, 17.1).
        Schema::create('document_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->restrictOnDelete();
            $table->string('kind', 12)->default('eingang');          // eingang, rueckzahlung
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);                        // immer positiv, die Art bestimmt die Richtung
            $table->string('method', 20)->default('ueberweisung');
            $table->string('reference', 100)->nullable();            // Verwendungszweck, Belegnummer der Bank …
            $table->string('note', 255)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['document_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_payments');
    }
};
