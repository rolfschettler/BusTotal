<?php

namespace App\Console\Commands;

use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Baut die Datenbanken ALLER aktiven Firmen auf den neuesten Stand (bei jedem Update nötig).
 * Fällt eine Firma aus, werden die übrigen trotzdem migriert; am Ende steht eine Fehlerliste.
 */
class TenantMigrate extends Command
{
    protected $signature = 'tenant:migrate {--tenant= : Nur diese Firma (Unterdomäne)}';

    protected $description = 'Führt die Datenbank-Migrationen für alle aktiven Firmen aus';

    public function handle(TenantManager $manager): int
    {
        $count = 0;
        $failures = $manager->each(function ($tenant) use (&$count) {
            Artisan::call('migrate', ['--database' => config('tenancy.tenant_connection'), '--force' => true]);
            $tenant->forceFill(['migrated_at' => now()])->save();
            $this->line("  {$tenant->subdomain}: ok");
            $count++;
        }, $this->option('tenant') ?: null);

        foreach ($failures as $subdomain => $message) {
            $this->error("  {$subdomain}: FEHLER – {$message}");
        }
        $this->info("{$count} Firma(en) migriert, ".count($failures).' mit Fehler.');

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
