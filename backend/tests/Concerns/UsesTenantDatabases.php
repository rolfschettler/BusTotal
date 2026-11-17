<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Für Tests mit echter Mandantentrennung: legt die zentrale Datenbank und leere Firmen-Datenbanken
 * (bustotal_test_central, _a, _b) selbst an und löscht sie danach wieder.
 */
trait UsesTenantDatabases
{
    private const DATABASES = ['bustotal_test_central', 'bustotal_test_a', 'bustotal_test_b', 'bustotal_test_c'];

    protected function setUpTenancy(): void
    {
        config(['tenancy.enabled' => true, 'tenancy.base_domain' => 'localhost', 'tenancy.local_fallback' => null]);
        $this->resetDatabases();

        Artisan::call('migrate', [
            '--database' => 'central', '--path' => 'database/migrations/central', '--realpath' => false, '--force' => true,
        ]);
    }

    protected function tearDownTenancy(): void
    {
        app(TenantManager::class)->deactivate();
        $this->resetDatabases(drop: true);
    }

    /** Datenbanken über die Standard-Testverbindung (Root) neu anlegen bzw. löschen. */
    protected function resetDatabases(bool $drop = false): void
    {
        $pdo = DB::connection('mysql')->getPdo();
        foreach (self::DATABASES as $name) {
            $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
            if (! $drop && $name !== 'bustotal_test_c') {
                $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
        DB::purge('central');
    }

    /** Zugangsdaten der Test-Datenbank-Verbindung (dieselben für alle Test-Firmen). */
    protected function dbCredentials(): array
    {
        return [
            'db_host' => config('database.connections.mysql.host'),
            'db_port' => (int) config('database.connections.mysql.port'),
            'db_username' => config('database.connections.mysql.username'),
            'db_password' => (string) config('database.connections.mysql.password'),
        ];
    }

    /** Legt eine Firma mit eigener Datenbank, Grundausstattung und einem Administrator an. */
    protected function makeTenant(string $subdomain, string $database): Tenant
    {
        $creds = $this->dbCredentials();
        $this->artisan('tenant:create', [
            'name' => 'Firma '.strtoupper($subdomain),
            'subdomain' => $subdomain,
            '--db-name' => $database,
            '--db-user' => $creds['db_username'],
            '--db-password' => $creds['db_password'],
            '--db-host' => $creds['db_host'],
            '--db-port' => $creds['db_port'],
        ])->assertSuccessful();

        $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();
        $this->inTenant($tenant, fn () => User::create([
            'name' => 'Admin '.$subdomain, 'email' => "admin@{$subdomain}.test", 'password' => 'geheim-12345',
            'role' => Role::Admin, 'active' => true, 'must_change_password' => false,
        ]));

        return $tenant;
    }

    protected function inTenant(Tenant $tenant, callable $callback): mixed
    {
        $manager = app(TenantManager::class);
        $manager->activate($tenant);
        try {
            return $callback();
        } finally {
            $manager->deactivate();
        }
    }
}
