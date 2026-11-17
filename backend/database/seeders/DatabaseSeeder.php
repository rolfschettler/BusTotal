<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Entwicklungs-Benutzer: je Rolle einer. Passwort für alle: "passwort" (nur lokal!). */
    public function run(): void
    {
        $this->call(SettingsSeeder::class);

        foreach (Role::cases() as $role) {
            User::updateOrCreate(
                ['email' => $role->value.'@bustotal.test'],
                ['name' => ucfirst($role->value), 'password' => 'passwort', 'role' => $role, 'active' => true],
            );
        }
    }
}
