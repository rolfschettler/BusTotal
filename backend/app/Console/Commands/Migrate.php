<?php

namespace App\Console\Commands;

use Illuminate\Database\Console\Migrations\MigrateCommand;

/**
 * Ersetzt `php artisan migrate`, damit es nur noch diesen einen Befehl für Datenbank-Updates gibt.
 *
 * Ohne Angaben und auf der zentralen Verbindung (so läuft die Anwendung) baut er erst die zentrale Datenbank auf
 * und danach die Datenbanken aller Firmen (`tenant:migrate`). Das Original würde hier die Firmen-Migrationen in die
 * zentrale Datenbank schreiben und die Firmen-Datenbanken unberührt lassen.
 *
 * Wer `--database` oder `--path` angibt, bekommt das Verhalten des Originals; ebenso läuft alles andere Gewohnte
 * (`migrate:fresh` in den Tests, `tenant:migrate` selbst) unverändert, denn dort ist die Verbindung eine andere.
 */
class Migrate extends MigrateCommand
{
    public function handle()
    {
        $explicit = $this->option('database') !== null || $this->option('path') !== [] || $this->option('pretend');
        $central = config('tenancy.central_connection');

        if ($explicit || ! config('tenancy.enabled') || config('database.default') !== $central) {
            return parent::handle();
        }

        $this->components->info('Zentrale Datenbank');
        $this->input->setOption('database', $central);
        $this->input->setOption('path', ['database/migrations/central']);
        $result = parent::handle();

        if ($result !== self::SUCCESS && $result !== null) {
            return $result;
        }

        $this->components->info('Datenbanken der Firmen');

        return $this->call('tenant:migrate');
    }
}
