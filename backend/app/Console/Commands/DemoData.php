<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsTenant;
use App\Services\DemoDataService;
use App\Services\DemoDocumentService;
use App\Services\DemoFleetService;
use Illuminate\Console\Command;

/**
 * Demo-Daten für Schulungen, Messen und eigene Tests: Kunden und Fuhrpark (Fahrzeuge, Fahrer, Abwesenheiten).
 *
 *   php artisan bustotal:demo-daten                  200 Kunden, 14 Fahrzeuge, 20 Fahrer
 *   php artisan bustotal:demo-daten --art=fuhrpark   nur Fahrzeuge und Fahrer
 *   php artisan bustotal:demo-daten --art=belege     10 Belegketten (Angebot bis Rechnung, Zahlungen, Storno) für die Demo-Kunden
 *   php artisan bustotal:demo-daten --anzahl=500     500 Kunden (die ersten 200 bleiben dieselben)
 *   php artisan bustotal:demo-daten --entfernen      entfernt alle Demo-Daten wieder (mit --art nur eine Art)
 */
class DemoData extends Command
{
    protected $signature = 'bustotal:demo-daten
        {--art=alles : Was: kunden, fuhrpark, belege oder alles = Kunden und Fuhrpark (Belege nur mit --art=belege, brauchen Demo-Kunden)}
        {--anzahl=200 : Wie viele Demo-Kunden es insgesamt geben soll (1 bis 5000)}
        {--fahrzeuge=14 : Wie viele Demo-Fahrzeuge es insgesamt geben soll (0 bis 200)}
        {--fahrer=20 : Wie viele Demo-Fahrer es insgesamt geben soll (0 bis 500)}
        {--entfernen : Demo-Daten entfernen statt anzulegen}
        {--force : Auch in einer produktiven Umgebung ausführen}
        {--tenant= : Unterdomäne der Firma}';

    protected $description = 'Legt realistische Demo-Daten an (Kunden, Fuhrpark) oder entfernt sie wieder';

    use SelectsTenant;

    public function handle(DemoDataService $demo, DemoFleetService $fleet, DemoDocumentService $documents): int
    {
        if ($code = $this->selectTenant()) {
            return $code;
        }

        $art = (string) $this->option('art');
        if (! in_array($art, ['kunden', 'fuhrpark', 'belege', 'alles'], true)) {
            $this->error('--art muss kunden, fuhrpark, belege oder alles sein.');

            return self::FAILURE;
        }
        $withCustomers = in_array($art, ['kunden', 'alles'], true);
        $withFleet = in_array($art, ['fuhrpark', 'alles'], true);
        $withDocuments = $art === 'belege';

        // Schutz: Auf dem echten Server sollen Demo-Kunden nicht versehentlich die Nummernkreise verbrauchen.
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Das ist eine produktive Umgebung. Demo-Daten verbrauchen echte Kunden- und Debitorennummern.');
            $this->line('Wenn du das wirklich willst (z. B. für eine Demo-Firma), füge --force hinzu.');

            return self::FAILURE;
        }

        if ($this->option('entfernen')) {
            if ($withDocuments && $documents->exists()) {
                $this->error('Festgeschriebene Demo-Belege lassen sich nicht löschen (Absicht, GoBD). Zum Neubeginn die Firmen-Datenbank neu aufbauen.');

                return self::FAILURE;
            }
            if ($withCustomers && $documents->exists()) {
                $this->error('Demo-Kunden mit Belegen lassen sich nicht entfernen. Zum Neubeginn die Firmen-Datenbank neu aufbauen.');

                return self::FAILURE;
            }
            if ($withCustomers) {
                $result = $demo->remove();
                $this->info("{$result['removed']} Demo-Kunden entfernt.");
                if ($result['counters_reset']) {
                    $this->line('Es gibt keine Kunden mehr: Kunden- und Debitorennummern beginnen wieder am Anfang.');
                } elseif ($result['removed'] > 0) {
                    $this->line('Es gibt noch echte Kunden: Die Nummernzähler bleiben unverändert.');
                }
            }
            if ($withFleet) {
                $result = $fleet->remove();
                $this->info("{$result['vehicles']} Demo-Fahrzeuge und {$result['drivers']} Demo-Fahrer (mit Abwesenheiten) entfernt.");
            }

            return self::SUCCESS;
        }

        $count = (int) $this->option('anzahl');
        $vehicles = (int) $this->option('fahrzeuge');
        $drivers = (int) $this->option('fahrer');
        if ($withCustomers && ($count < 1 || $count > 5000)) {
            $this->error('--anzahl muss zwischen 1 und 5000 liegen.');

            return self::FAILURE;
        }
        if ($withFleet && ($vehicles < 0 || $vehicles > 200 || $drivers < 0 || $drivers > 500)) {
            $this->error('--fahrzeuge muss zwischen 0 und 200 liegen, --fahrer zwischen 0 und 500.');

            return self::FAILURE;
        }

        if ($withCustomers) {
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
        }

        if ($withFleet) {
            $stats = $fleet->generate($vehicles, $drivers);
            $this->info("{$stats['vehicles']} Demo-Fahrzeuge und {$stats['drivers']} Demo-Fahrer angelegt, dazu {$stats['absences']} Abwesenheiten ({$stats['skipped']} waren schon vorhanden).");
            if ($stats['conflicts'] > 0) {
                $this->warn("{$stats['conflicts']} übersprungen, weil Fuhrpark-/Personalnummer oder Kennzeichen schon an einen echten Datensatz vergeben sind.");
            }
        }
        if ($withDocuments) {
            if ($documents->exists()) {
                $this->line('Es gibt schon Demo-Belege, es werden keine weiteren angelegt.');
            } else {
                $stats = $documents->generate();
                $this->info("{$stats['chains']} Belegketten mit {$stats['documents']} Belegen angelegt ({$stats['skipped']} Ketten übersprungen).");
            }
        }
        $this->line('Entfernen: php artisan bustotal:demo-daten --entfernen');

        return self::SUCCESS;
    }
}
