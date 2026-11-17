<?php

namespace App\Tenancy;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Legt Firmen an und richtet sie ein. Gemeinsamer Weg für den Befehl tenant:create und die
 * Superadmin-Oberfläche. Fehler kommen als TenantException mit einer verständlichen deutschen Meldung.
 */
class TenantProvisioner
{
    public function __construct(private TenantManager $manager) {}

    /**
     * @param  array{name:string,subdomain:string,db_host:string,db_port:int,db_database:string,db_username:string,db_password:?string}  $data
     */
    public function create(array $data): Tenant
    {
        $data['subdomain'] = mb_strtolower($data['subdomain']);
        $this->checkSubdomain($data['subdomain']);
        $this->checkDatabaseFree($data['db_host'], (int) $data['db_port'], $data['db_database']);

        $tenant = new Tenant($data + ['is_active' => true]);
        $this->checkConnection($tenant);

        // Schutz vor einem Vertipper: Eine Datenbank mit fremden Tabellen wird nicht angefasst.
        $existing = DB::build($this->manager->connectionConfig($tenant))->select('SHOW TABLES');
        if (count($existing) > 0) {
            throw new TenantException('Die Datenbank ist nicht leer. Bitte eine neue, leere Datenbank verwenden.');
        }

        $tenant->save();

        try {
            $this->manager->activate($tenant);
            Artisan::call('migrate', ['--database' => config('tenancy.tenant_connection'), '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SettingsSeeder', '--force' => true]);
            CompanySetting::current()->update(['name' => $tenant->name]);
            $tenant->forceFill(['migrated_at' => now()])->save();
        } catch (Throwable $e) {
            report($e);
            throw new TenantException('Der Aufbau der Datenbank ist fehlgeschlagen ('.$e->getMessage().'). Die Firma ist gespeichert, aber nicht fertig: Nach der Korrektur "Datenbank aktualisieren" ausführen.', previous: $e);
        } finally {
            $this->manager->deactivate();
        }

        return $tenant;
    }

    /** Datenbank der Firma auf den neuesten Stand bringen. */
    public function migrate(Tenant $tenant): void
    {
        try {
            $this->manager->activate($tenant);
            Artisan::call('migrate', ['--database' => config('tenancy.tenant_connection'), '--force' => true]);
            $tenant->forceFill(['migrated_at' => now()])->save();
        } catch (Throwable $e) {
            report($e);
            throw new TenantException('Die Datenbank konnte nicht aktualisiert werden: '.$e->getMessage(), previous: $e);
        } finally {
            $this->manager->deactivate();
        }
    }

    /** Prüft die (geänderten) Zugangsdaten einer vorhandenen Firma. */
    public function checkConnection(Tenant $tenant): void
    {
        $error = $this->manager->testConnection($this->manager->connectionConfig($tenant));
        if ($error !== null) {
            throw new TenantException('Verbindung zur Firmen-Datenbank fehlgeschlagen: '.$error);
        }
    }

    /**
     * Legt in der Datenbank der Firma einen Administrator mit vorläufigem Passwort an.
     * Gibt das Passwort zurück (wird nirgends gespeichert).
     */
    public function createAdmin(Tenant $tenant, string $email, string $name = 'Administrator'): string
    {
        $email = mb_strtolower($email);

        try {
            $this->manager->activate($tenant);

            $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'unique:users,email']]);
            if ($validator->fails()) {
                throw new TenantException($validator->errors()->first());
            }

            $temporary = TemporaryPassword::generate();
            User::create([
                'name' => $name, 'email' => $email, 'password' => $temporary,
                'role' => Role::Admin, 'active' => true, 'must_change_password' => true,
            ]);

            return $temporary;
        } finally {
            $this->manager->deactivate();
        }
    }

    public function checkSubdomain(string $subdomain, ?string $ignoreId = null): void
    {
        if (preg_match(TenantManager::SUBDOMAIN_PATTERN, $subdomain) !== 1) {
            throw new TenantException('Die Unterdomäne darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten (nicht am Anfang oder Ende).');
        }
        if (in_array($subdomain, config('tenancy.reserved_subdomains'), true) || $subdomain === config('tenancy.superadmin_subdomain')) {
            throw new TenantException("\"{$subdomain}\" ist für BusTotal reserviert.");
        }
        if (Tenant::where('subdomain', $subdomain)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            throw new TenantException("Die Unterdomäne \"{$subdomain}\" gibt es schon.");
        }
    }

    /** Eine Datenbank darf nur einer Firma gehören, und niemals der zentralen. */
    private function checkDatabaseFree(string $host, int $port, string $database): void
    {
        $central = config('database.connections.'.config('tenancy.central_connection'));
        $isCentral = $database === $central['database'] && $host === $central['host'] && $port === (int) $central['port'];

        if ($isCentral || Tenant::where('db_database', $database)->where('db_host', $host)->exists()) {
            throw new TenantException("Die Datenbank \"{$database}\" ist schon vergeben (zentrale Datenbank oder andere Firma).");
        }
    }
}
