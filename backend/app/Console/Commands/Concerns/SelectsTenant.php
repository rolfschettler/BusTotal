<?php

namespace App\Console\Commands\Concerns;

use App\Models\Tenant;
use App\Tenancy\TenantManager;

/**
 * Für Befehle, die mit den Daten EINER Firma arbeiten (Administrator anlegen, Demo-Daten ...).
 * Der Befehl braucht dafür die Option {--tenant= : Unterdomäne der Firma}.
 */
trait SelectsTenant
{
    /** Schaltet auf die gewählte Firma um. Gibt einen Fehlercode zurück, wenn das nicht geht. */
    protected function selectTenant(): ?int
    {
        $manager = app(TenantManager::class);
        if (! $manager->enabled()) {
            return null;
        }

        $subdomain = $this->option('tenant');
        if (! $subdomain) {
            $this->error('Bitte die Firma angeben: --tenant=<unterdomäne> (Liste: php artisan tenant:list).');

            return self::FAILURE;
        }

        $tenant = Tenant::where('subdomain', $subdomain)->first();
        if (! $tenant) {
            $this->error("Es gibt keine Firma mit der Unterdomäne \"{$subdomain}\".");

            return self::FAILURE;
        }

        $manager->activate($tenant);
        $this->line("Firma: {$tenant->name} ({$tenant->subdomain})");

        return null;
    }
}
