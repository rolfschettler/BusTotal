<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 30)->index();                 // angebot, auftragsbestaetigung, anzahlung, rechnung, gutschrift
            $table->string('status', 20)->default('entwurf')->index();
            // Erst beim Festschreiben vergeben. Mehrere NULL sind in einem UNIQUE-Index erlaubt.
            $table->string('number', 30)->nullable()->unique();

            // Kunde (Verweis, solange Entwurf). Beim Festschreiben wird der Stand in "recipient" kopiert.
            $table->foreignUuid('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('source_id')->nullable()->constrained('documents')->restrictOnDelete(); // Vorgängerbeleg
            $table->foreignUuid('cancels_id')->nullable()->constrained('documents')->restrictOnDelete(); // Storno von …
            $table->uuid('cancelled_by_id')->nullable();         // … Storno-Beleg zu diesem Beleg

            // Unveränderliche Kopien zum Zeitpunkt des Festschreibens (GoBD): Absender und Empfänger.
            $table->json('seller')->nullable();
            $table->json('recipient')->nullable();

            $table->string('subject')->nullable();               // Betreff
            $table->text('intro_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('notes')->nullable();                   // intern, erscheint nicht auf dem Beleg

            $table->date('document_date')->nullable();
            $table->date('service_date_from')->nullable();       // Leistungszeitpunkt = Fahrtdatum (§ 14 Abs. 4 Nr. 6 UStG)
            $table->date('service_date_to')->nullable();
            $table->unsignedSmallInteger('payment_term_days')->nullable();
            $table->date('due_date')->nullable();

            $table->string('currency', 3)->default('EUR');
            $table->decimal('net_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('gross_total', 12, 2)->default(0);
            $table->json('tax_summary')->nullable();             // [{rate, net, tax}] je Steuersatz

            $table->timestamp('finalized_at')->nullable();
            $table->uuid('finalized_by')->nullable();
            $table->timestamps();

            $table->index('document_date');
        });

        Schema::create('document_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 20)->default('Stk');
            $table->decimal('unit_price', 12, 2);                // netto
            $table->decimal('tax_rate', 5, 2);                   // Prozent, je Position einstellbar
            $table->string('tax_note')->nullable();              // z. B. Grund für 0 % (Ausland, steuerfrei)
            $table->decimal('net_amount', 12, 2)->default(0);    // Menge × Einzelpreis, kaufmännisch gerundet
            $table->timestamps();

            $table->unique(['document_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_lines');
        Schema::dropIfExists('documents');
    }
};
