<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Legt eine neue Firma an. Die leere Datenbank wird vorher im Kundencenter des Hosters angelegt
 * (auf dem Shared-Hosting kann die Anwendung selbst keine Datenbanken erzeugen).
 *
 *   php artisan tenant:create "Muster Reisen" muster --db-name=... --db-user=... --db-password=...
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

    public function handle(TenantManager $manager): int
    {
        $subdomain = mb_strtolower((string) $this->argument('subdomain'));

        if (! $this->checkSubdomain($subdomain)) {
            return self::FAILURE;
        }

        $database = (string) $this->option('db-name');
        $username = (string) $this->option('db-user');
        if ($database === '' || $username === '') {
            $this->error('--db-name und --db-user sind nötig.');

            return self::FAILURE;
        }

        // Eine Datenbank darf nur einer Firma gehören, und niemals der zentralen.
        $central = config('database.connections.'.config('tenancy.central_connection'));
        $host = (string) $this->option('db-host');
        $port = (int) $this->option('db-port');
        $sameAsCentral = $database === $central['database'] && $host === $central['host'] && $port === (int) $central['port'];
        if ($sameAsCentral || Tenant::where('db_database', $database)->where('db_host', $host)->exists()) {
            $this->error("Die Datenbank \"{$database}\" ist schon vergeben (zentrale Datenbank oder andere Firma).");

            return self::FAILURE;
        }

        $password = $this->option('db-password') ?? (string) $this->secret('Datenbank-Passwort');

        $tenant = new Tenant([
            'name' => (string) $this->argument('name'),
            'subdomain' => $subdomain,
            'db_host' => $host,
            'db_port' => $port,
            'db_database' => $database,
            'db_username' => $username,
            'db_password' => $password,
            'is_active' => true,
        ]);

        $error = $manager->testConnection($manager->connectionConfig($tenant));
        if ($error !== null) {
            $this->error('Verbindung zur Firmen-Datenbank fehlgeschlagen: '.$error);

            return self::FAILURE;
        }

        // Schutz vor einem Vertipper: Eine Datenbank mit fremden Tabellen wird nicht angefasst.
        $existing = DB::build($manager->connectionConfig($tenant))->select('SHOW TABLES');
        if (count($existing) > 0) {
            $this->error('Die Datenbank ist nicht leer. Bitte eine neue, leere Datenbank verwenden.');

            return self::FAILURE;
        }

        $tenant->save();

        try {
            $manager->activate($tenant);
            Artisan::call('migrate', ['--database' => config('tenancy.tenant_connection'), '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SettingsSeeder', '--force' => true]);
            \App\Models\CompanySetting::current()->update(['name' => $tenant->name]);
            $tenant->forceFill(['migrated_at' => now()])->save();

            $this->info("Firma \"{$tenant->name}\" angelegt ({$subdomain}.".config('tenancy.base_domain').').');
        } catch (Throwable $e) {
            $manager->deactivate();
            $this->error('Aufbau der Datenbank fehlgeschlagen: '.$e->getMessage());
            $this->line('Die Firma ist gespeichert, aber nicht fertig. Nach der Korrektur: php artisan tenant:migrate --tenant='.$subdomain);

            return self::FAILURE;
        }

        if ($email = $this->option('admin-email')) {
            $this->call('bustotal:admin-anlegen', ['email' => $email, '--tenant' => $subdomain]);
        } else {
            $this->line("Ersten Administrator anlegen: php artisan bustotal:admin-anlegen <e-mail> --tenant={$subdomain}");
        }
        $manager->deactivate();

        return self::SUCCESS;
    }

    private function checkSubdomain(string $subdomain): bool
    {
        if (preg_match(TenantManager::SUBDOMAIN_PATTERN, $subdomain) !== 1) {
            $this->error('Die Unterdomäne darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten (nicht am Anfang oder Ende).');

            return false;
        }
        if (in_array($subdomain, config('tenancy.reserved_subdomains'), true)) {
            $this->error("\"{$subdomain}\" ist für BusTotal reserviert.");

            return false;
        }
        if (Tenant::where('subdomain', $subdomain)->exists()) {
            $this->error("Die Unterdomäne \"{$subdomain}\" gibt es schon.");

            return false;
        }

        return true;
    }
}
