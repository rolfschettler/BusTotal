<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Vorläufiges Passwort, das der Betreiber einem Administrator genannt hat. Verschlüsselt (APP_KEY), nur gefüllt,
            // solange der Benutzer es noch nicht geändert hat. Beim ersten eigenen Passwort wird es gelöscht.
            $table->text('temporary_password')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('temporary_password');
        });
    }
};
