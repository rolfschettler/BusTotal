<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Grundausstattung (Steuersätze, Nummernkreise): immer.
        $this->call(SettingsSeeder::class);

        // Testbenutzer mit bekanntem Passwort: NUR lokal. Auf einem echten Server würde das jedem
        // Fremden einen Zugang geben. Dort legt man den ersten Administrator mit
        // "php artisan bustotal:admin-anlegen" an.
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Keine Testbenutzer angelegt (Umgebung: '.app()->environment().'). Ersten Administrator mit "php artisan bustotal:admin-anlegen" anlegen.');

            return;
        }

        // Je Rolle ein Benutzer. Passwort für alle: "passwort".
        foreach (Role::cases() as $role) {
            User::updateOrCreate(
                ['email' => $role->value.'@bustotal.test'],
                ['name' => ucfirst($role->value), 'password' => 'passwort', 'role' => $role, 'active' => true, 'must_change_password' => false],
            );
        }
    }
}
