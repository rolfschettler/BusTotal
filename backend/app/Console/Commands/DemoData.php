<?php

namespace App\Console\Commands;

use App\Services\DemoDataService;
use Illuminate\Console\Command;

/**
 * Demo-Kunden für Schulungen, Messen und eigene Tests.
 *
 *   php artisan bustotal:demo-daten                 legt 200 Demo-Kunden an
 *   php artisan bustotal:demo-daten --anzahl=500    legt 500 an (die ersten 200 bleiben dieselben)
 *   php artisan bustotal:demo-daten --entfernen     entfernt alle Demo-Kunden wieder
 */
class DemoData extends Command
{
    protected $signature = 'bustotal:demo-daten
        {--anzahl=200 : Wie viele Demo-Kunden es insgesamt geben soll (1 bis 5000)}
        {--entfernen : Alle Demo-Kunden entfernen statt anzulegen}
        {--force : Auch in einer produktiven Umgebung ausführen}';

    protected $description = 'Legt realistische Demo-Kunden an (oder entfernt sie wieder)';

    public function handle(DemoDataService $demo): int
    {
        // Schutz: Auf dem echten Server sollen Demo-Kunden nicht versehentlich die Nummernkreise verbrauchen.
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Das ist eine produktive Umgebung. Demo-Daten verbrauchen echte Kunden- und Debitorennummern.');
            $this->line('Wenn du das wirklich willst (z. B. für eine Demo-Firma), füge --force hinzu.');

            return self::FAILURE;
        }

        if ($this->option('entfernen')) {
            $result = $demo->remove();
            $this->info("{$result['removed']} Demo-Kunden entfernt.");
            if ($result['counters_reset']) {
                $this->line('Es gibt keine Kunden mehr: Kunden- und Debitorennummern beginnen wieder am Anfang.');
            } elseif ($result['removed'] > 0) {
                $this->line('Es gibt noch echte Kunden: Die Nummernzähler bleiben unverändert.');
            }

            return self::SUCCESS;
        }

        $count = (int) $this->option('anzahl');
        if ($count < 1 || $count > 5000) {
            $this->error('--anzahl muss zwischen 1 und 5000 liegen.');

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();
        $stats = $demo->generate($count, fn () => $bar->advance());
        $bar->finish();
        $this->newLine(2);

        $this->info("{$stats['created']} Demo-Kunden angelegt, {$stats['skipped']} waren schon vorhanden.");
        if ($stats['created'] > 0) {
            $this->line("Kundennummern {$stats['first_number']} bis {$stats['last_number']}.");
            $this->line("Dazu {$stats['contacts']} Ansprechpartner, {$stats['addresses']} Abholadressen, {$stats['archived']} archivierte Kunden.");
        }
        $this->line('Entfernen: php artisan bustotal:demo-daten --entfernen');

        return self::SUCCESS;
    }
}
