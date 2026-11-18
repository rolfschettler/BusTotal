<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsTenant;
use App\Enums\Role;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Legt den ersten Administrator einer Firma an. Gebraucht, weil es auf einem frischen Server noch
 * keinen Benutzer gibt, der in der Oberfläche weitere Benutzer anlegen könnte.
 */
class CreateAdmin extends Command
{
    use SelectsTenant;

    protected $signature = 'bustotal:admin-anlegen {email : E-Mail-Adresse des Administrators} {name=Administrator : Anzeigename} {--tenant= : Unterdomäne der Firma}';

    protected $description = 'Legt einen Administrator mit vorläufigem Passwort an (muss beim ersten Login geändert werden)';

    public function handle(): int
    {
        if ($code = $this->selectTenant()) {
            return $code;
        }

        $email = mb_strtolower((string) $this->argument('email'));

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'unique:users,email']]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $temporary = TemporaryPassword::generate();

        $user = new User([
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'role' => Role::Admin,
            'active' => true,
        ]);
        $user->setRetrievableTemporaryPassword($temporary);
        $user->save();

        $this->info("Administrator {$email} angelegt.");
        $this->line("Vorläufiges Passwort (wird nur jetzt angezeigt): {$temporary}");
        $this->line('Es muss beim ersten Anmelden geändert werden.');

        return self::SUCCESS;
    }
}
