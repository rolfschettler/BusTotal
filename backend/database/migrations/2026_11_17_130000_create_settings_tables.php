<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Genau eine Zeile je Mandanten-Datenbank.
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // Firmenname
            $table->string('legal_form', 50)->nullable(); // Rechtsform, z. B. GmbH
            $table->string('street')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_number', 30)->nullable();      // Steuernummer
            $table->string('vat_id', 20)->nullable();          // USt-IdNr.
            $table->string('commercial_register')->nullable(); // Handelsregister
            $table->string('managing_director')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('bic', 11)->nullable();
            $table->text('letterhead_note')->nullable();       // Zusatz im Briefkopf
            $table->text('footer_text')->nullable();           // Fußzeile auf Belegen
            $table->unsignedSmallInteger('payment_term_days')->default(14);
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->decimal('rate', 5, 2);                // Prozent
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('number_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->unique();         // rechnung, angebot, ...
            $table->string('label', 60);
            $table->string('prefix', 20)->default('');
            $table->boolean('include_year')->default(true);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedSmallInteger('last_year')->nullable(); // Jahr der letzten Vergabe
            $table->boolean('reset_yearly')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_ranges');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('company_settings');
    }
};
