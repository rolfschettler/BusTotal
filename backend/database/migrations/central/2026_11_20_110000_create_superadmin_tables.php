<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Zentrale Tabellen für die Superadmin-Oberfläche (Betreiber von BusTotal, nicht die Firmen):
| Konten, Anmelde-Token, Protokoll und der Zwischenspeicher (für die Anmeldebremse).
*/
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $schema = Schema::connection('central');

        $schema->create('superadmins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        $schema->create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        // Nur anfügen, nie ändern (wie das Protokoll der Firmen).
        $schema->create('superadmin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('superadmin_id')->nullable()->index();
            $table->string('event', 64)->index();
            $table->uuid('tenant_id')->nullable()->index();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $schema->create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });
        $schema->create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('central');
        foreach (['cache_locks', 'cache', 'superadmin_audit_logs', 'personal_access_tokens', 'superadmins'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
