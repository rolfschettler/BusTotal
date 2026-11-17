<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('customer_number', 30)->unique();          // z. B. K-00001
            $table->unsignedBigInteger('debitor_number')->nullable()->unique(); // DATEV-Debitorenkonto
            $table->string('type', 20)->index();                      // firma, privat, schule, verein, reisebuero
            $table->string('company_name')->nullable()->index();
            $table->string('salutation', 20)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('vat_id', 20)->nullable();
            // Rechnungsadresse
            $table->string('street')->nullable();
            $table->string('postal_code', 10)->nullable()->index();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');
            // leer = Standard-Zahlungsziel aus den Firmeneinstellungen
            $table->unsignedSmallInteger('payment_term_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();                                    // Archivieren statt Löschen (GoBD)
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->string('salutation', 20)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name');
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Weitere Adressen, z. B. Abholpunkte für Busreisen
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('abholung');          // abholung, lieferung, sonstige
            $table->string('label')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('customers');
    }
};
