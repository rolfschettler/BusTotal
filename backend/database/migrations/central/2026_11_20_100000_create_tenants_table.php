<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| ZENTRALE Migration (Ordner database/migrations/central). Läuft nur gegen die zentrale Datenbank:
|   php artisan migrate --database=central --path=database/migrations/central --force
| Alle anderen Migrationen in database/migrations gehören zu den Mandanten-Datenbanken
| (php artisan tenant:migrate).
*/
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // Teil des Hostnamens: <subdomain>.<basis-domäne>
            $table->string('subdomain', 63)->unique();
            // Zugangsdaten der Mandanten-Datenbank (Passwort verschlüsselt, siehe Modell Tenant).
            $table->string('db_host');
            $table->unsignedSmallInteger('db_port')->default(3306);
            // Eine Datenbank gehört genau einem Mandanten.
            $table->string('db_database')->unique();
            $table->string('db_username');
            $table->text('db_password')->nullable();
            // Gesperrte Mandanten erreichen die Anwendung nicht (z. B. nicht bezahlt), die Daten bleiben.
            $table->boolean('is_active')->default(true);
            $table->timestamp('migrated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenants');
    }
};
