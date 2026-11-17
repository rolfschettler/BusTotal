<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantException;
use App\Tenancy\TenantProvisioner;
use Illuminate\Console\Command;

/**
 * Legt eine neue Firma an. Die leere Datenbank wird vorher im Kundencenter des Hosters angelegt
 * (auf dem Shared-Hosting kann die Anwendung selbst keine Datenbanken erzeugen).
 *
 *   php artisan tenant:create "Muster Reisen" muster --db-name=... --db-user=... --db-password=...
 *
 * Dasselbe geht in der Superadmin-Oberfläche; beide benutzen den TenantProvisioner.
 */
class TenantCreate extends Command
{
    protected $signature = 'tenant:create
        {name : Name der Firma}
        {subdomain : Unterdomäne, z. B. "muster" für muster.bustotal.de}
        {--db-host=127.0.0.1}
        {--db-port=3306}
        {--db-name= : Name der (leeren) Datenbank der Firma}
        {--db-user= : Datenbank-Benutzer}
        {--db-password= : Datenbank-Passwort (wenn weggelassen, wird danach gefragt)}
        {--admin-email= : Legt gleich den ersten Administrator an}';

    protected $description = 'Legt eine Firma an: Zugangsdaten prüfen, Datenbank aufbauen, Grundausstattung füllen';

    public function handle(TenantProvisioner $provisioner): int
    {
        $database = (string) $this->option('db-name');
        $username = (string) $this->option('db-user');
        if ($database === '' || $username === '') {
            $this->error('--db-name und --db-user sind nötig.');

            return self::FAILURE;
        }

        $password = $this->option('db-password') ?? (string) $this->secret('Datenbank-Passwort');

        try {
            $tenant = $provisioner->create([
                'name' => (string) $this->argument('name'),
                'subdomain' => (string) $this->argument('subdomain'),
                'db_host' => (string) $this->option('db-host'),
                'db_port' => (int) $this->option('db-port'),
                'db_database' => $database,
                'db_username' => $username,
                'db_password' => $password,
            ]);
        } catch (TenantException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Firma \"{$tenant->name}\" angelegt ({$tenant->subdomain}.".config('tenancy.base_domain').').');

        if ($email = $this->option('admin-email')) {
            return $this->call('bustotal:admin-anlegen', ['email' => $email, '--tenant' => $tenant->subdomain]);
        }
        $this->line("Ersten Administrator anlegen: php artisan bustotal:admin-anlegen <e-mail> --tenant={$tenant->subdomain}");

        return self::SUCCESS;
    }
}
