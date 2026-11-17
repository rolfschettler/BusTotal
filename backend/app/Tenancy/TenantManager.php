<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Schaltet die Anwendung auf die Datenbank EINES Mandanten um.
 *
 * Nach activate() zeigt die Standardverbindung auf die Mandanten-Datenbank. Dadurch arbeiten alle
 * Modelle (Kunden, Benutzer, Anmelde-Token ...) automatisch nur mit den Daten dieser Firma.
 * Vor activate() zeigt die Standardverbindung auf die zentrale Datenbank, wo es keine Kundentabellen
 * gibt: Ein vergessener Mandant führt dann zu einem lauten Fehler statt zu fremden Daten.
 */
class TenantManager
{
    private ?Tenant $current = null;

    private ?string $originalDefault = null;

    public function enabled(): bool
    {
        return (bool) config('tenancy.enabled');
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    /** Unterdomäne aus dem Host holen: "firma.bustotal.de" -> "firma". Sonst null. */
    public function subdomainFromHost(string $host): ?string
    {
        $host = strtolower($host);
        $suffix = '.'.strtolower((string) config('tenancy.base_domain'));

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $sub = substr($host, 0, -strlen($suffix));

        // Genau eine Ebene und nur erlaubte Zeichen (kein "a.b", keine Sonderzeichen).
        return preg_match(self::SUBDOMAIN_PATTERN, $sub) === 1 ? $sub : null;
    }

    public const SUBDOMAIN_PATTERN = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

    /** Verbindungseinstellungen eines Mandanten (Passwort wird hier entschlüsselt). */
    public function connectionConfig(Tenant $tenant): array
    {
        return array_merge(config('database.connections.'.config('tenancy.tenant_connection')), [
            'host' => $tenant->db_host,
            'port' => $tenant->db_port,
            'database' => $tenant->db_database,
            'username' => $tenant->db_username,
            'password' => $tenant->db_password ?? '',
        ]);
    }

    public function activate(Tenant $tenant): void
    {
        $name = config('tenancy.tenant_connection');
        $this->originalDefault ??= DB::getDefaultConnection();

        config(["database.connections.{$name}" => $this->connectionConfig($tenant)]);
        DB::purge($name);
        DB::setDefaultConnection($name);

        // Dateien (z. B. das Logo) je Mandant getrennt ablegen.
        config(['filesystems.disks.local.root' => storage_path('app/private/tenants/'.$tenant->id)]);

        $this->resetCachedServices();
        $this->current = $tenant;
    }

    /** Zurück zur zentralen Datenbank (Befehle, Tests, Schleifen über alle Mandanten). */
    public function deactivate(): void
    {
        if ($this->originalDefault !== null) {
            DB::purge(config('tenancy.tenant_connection'));
            DB::setDefaultConnection($this->originalDefault);
        }

        config(['filesystems.disks.local.root' => storage_path('app/private')]);
        $this->resetCachedServices();
        $this->current = null;
    }

    /** Für jeden aktiven Mandanten ausführen; Fehler eines Mandanten stoppen die anderen nicht. */
    public function each(callable $callback, ?string $onlySubdomain = null): array
    {
        $failures = [];
        $query = Tenant::query()->where('is_active', true)->orderBy('subdomain');
        if ($onlySubdomain !== null) {
            $query->where('subdomain', $onlySubdomain);
        }

        foreach ($query->get() as $tenant) {
            try {
                $this->activate($tenant);
                $callback($tenant);
            } catch (Throwable $e) {
                $failures[$tenant->subdomain] = $e->getMessage();
            } finally {
                $this->deactivate();
            }
        }

        return $failures;
    }

    /** Prüft, ob die Zugangsdaten funktionieren. Gibt null zurück oder die Fehlermeldung. */
    public function testConnection(array $config): ?string
    {
        try {
            DB::build($config)->getPdo();

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /** Bereits aufgelöste Dienste halten die alte Verbindung bzw. den alten Pfad fest. */
    private function resetCachedServices(): void
    {
        Storage::forgetDisk('local');
        Cache::forgetDriver();
        Auth::forgetGuards();
    }
}
