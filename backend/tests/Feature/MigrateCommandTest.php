<?php

namespace Tests\Feature;

use App\Console\Commands\Migrate;
use App\Console\Commands\TenantMigrate;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Es soll nur einen Befehl für Datenbank-Updates geben. Fällt der Ersatz weg, schreibt ein einfaches `migrate` die
 * Firmen-Tabellen in die zentrale Datenbank (siehe docs/MANDANTEN.md).
 */
class MigrateCommandTest extends TestCase
{
    public function test_migrate_ist_durch_den_projekt_befehl_ersetzt(): void
    {
        $this->assertInstanceOf(Migrate::class, Artisan::all()['migrate']);
        $this->assertInstanceOf(TenantMigrate::class, Artisan::all()['tenant:migrate']);
    }

    public function test_der_ersatz_behaelt_alle_optionen_des_originals(): void
    {
        $options = Artisan::all()['migrate']->getDefinition()->getOptions();

        foreach (['database', 'force', 'path', 'realpath', 'pretend', 'seed', 'step'] as $name) {
            $this->assertArrayHasKey($name, $options);
        }
    }
}
