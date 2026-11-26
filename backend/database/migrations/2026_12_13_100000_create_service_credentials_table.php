<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zugangsschlüssel zu externen Diensten je Firma (zunächst der Routing- und Adressdienst). Eine Firma ohne eigenen Eintrag
 * nutzt den allgemeinen Schlüssel aus der Server-Konfiguration als Rückfall. Der Schlüssel ist verschlüsselt (APP_KEY).
 * `service` ist ein Kennwort des Dienstes (heute nur „openrouteservice“), damit später ein anderer Dienst dazukommen kann.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service', 40)->unique();
            $table->text('api_key');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_credentials');
    }
};
