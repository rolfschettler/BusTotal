<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Services\CustomerService;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesTenantDatabases;
use Tests\TestCase;

/**
 * Mandantentrennung: Jede Firma hat eine eigene Datenbank, und nichts darf zwischen ihnen durchsickern.
 * Die Tests legen ihre Datenbanken selbst an (bustotal_test_central, ..._a, ..._b) und löschen sie wieder.
 */
class TenancyTest extends TestCase
{
    use UsesTenantDatabases;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_host_wird_zur_unterdomaene(): void
    {
        $m = app(TenantManager::class);

        $this->assertSame('firma', $m->subdomainFromHost('firma.localhost'));
        $this->assertSame('firma', $m->subdomainFromHost('FIRMA.Localhost'));
        $this->assertNull($m->subdomainFromHost('localhost'));
        $this->assertNull($m->subdomainFromHost('a.b.localhost'));
        $this->assertNull($m->subdomainFromHost('firma.example.com'));
        $this->assertNull($m->subdomainFromHost('evil_.localhost'));
        $this->assertNull($m->subdomainFromHost('-x.localhost'));
    }

    public function test_unbekannte_adresse_ist_404(): void
    {
        $this->makeTenant('a', 'bustotal_test_a');

        $this->getJson('http://unbekannt.localhost/api/branding')->assertNotFound();
        $this->getJson('http://localhost/api/branding')->assertNotFound();
    }

    public function test_gesperrte_firma_ist_nicht_erreichbar(): void
    {
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $this->getJson('http://a.localhost/api/branding')->assertOk()->assertJsonPath('name', 'Firma A');

        $a->update(['is_active' => false]);

        // Dieselbe Antwort wie bei einer unbekannten Adresse: Es wird nichts verraten.
        $this->getJson('http://a.localhost/api/branding')->assertNotFound()
            ->assertJsonPath('message', 'Diese Adresse gehört zu keiner Firma.');
    }

    public function test_jede_firma_sieht_nur_ihre_eigenen_daten(): void
    {
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $b = $this->makeTenant('b', 'bustotal_test_b');

        $this->inTenant($a, fn () => app(CustomerService::class)->create(['type' => 'privat', 'last_name' => 'Nur-in-A', 'first_name' => 'Anna']));

        $tokenA = $this->postJson('http://a.localhost/api/login', ['email' => 'admin@a.test', 'password' => 'geheim-12345'])
            ->assertOk()->json('token');
        $tokenB = $this->postJson('http://b.localhost/api/login', ['email' => 'admin@b.test', 'password' => 'geheim-12345'])
            ->assertOk()->json('token');

        $this->withToken($tokenA)->getJson('http://a.localhost/api/customers')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($tokenB)->getJson('http://b.localhost/api/customers')->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame(1, $this->inTenant($a, fn () => Customer::count()));
        $this->assertSame(0, $this->inTenant($b, fn () => Customer::count()));
        $this->assertSame('Firma B', $this->getJson('http://b.localhost/api/branding')->json('name'));
    }

    public function test_token_einer_firma_gilt_nicht_bei_einer_anderen(): void
    {
        $this->makeTenant('a', 'bustotal_test_a');
        $this->makeTenant('b', 'bustotal_test_b');

        $tokenA = $this->postJson('http://a.localhost/api/login', ['email' => 'admin@a.test', 'password' => 'geheim-12345'])->json('token');

        $this->withToken($tokenA)->getJson('http://a.localhost/api/me')->assertOk();
        // Das Token gibt es in der Datenbank von B nicht.
        $this->withToken($tokenA)->getJson('http://b.localhost/api/me')->assertUnauthorized();
    }

    public function test_anmeldung_gilt_nur_in_der_eigenen_firma(): void
    {
        $this->makeTenant('a', 'bustotal_test_a');
        $this->makeTenant('b', 'bustotal_test_b');

        $this->postJson('http://b.localhost/api/login', ['email' => 'admin@a.test', 'password' => 'geheim-12345'])
            ->assertStatus(422);
    }

    public function test_dateien_liegen_je_firma_in_einem_eigenen_ordner(): void
    {
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $b = $this->makeTenant('b', 'bustotal_test_b');

        $rootA = $this->inTenant($a, fn () => config('filesystems.disks.local.root'));
        $rootB = $this->inTenant($b, fn () => config('filesystems.disks.local.root'));

        $this->assertNotSame($rootA, $rootB);
        $this->assertStringContainsString($a->id, $rootA);
        $this->assertStringContainsString($b->id, $rootB);
    }

    public function test_das_datenbankpasswort_ist_verschluesselt(): void
    {
        $a = $this->makeTenant('a', 'bustotal_test_a');

        $raw = DB::connection('central')->table('tenants')->where('id', $a->id)->value('db_password');
        $password = (string) config('database.connections.mysql.password');

        $this->assertNotSame($password, $raw);
        $this->assertSame($password, $a->fresh()->db_password);
        $this->assertArrayNotHasKey('db_password', $a->fresh()->toArray());
    }

    public function test_tenant_create_lehnt_unsichere_angaben_ab(): void
    {
        $this->makeTenant('a', 'bustotal_test_a');
        $user = config('database.connections.mysql.username');
        $pw = (string) config('database.connections.mysql.password');
        $base = ['--db-user' => $user, '--db-password' => $pw];

        // reservierte und doppelte Unterdomäne, ungültige Schreibweise
        $this->artisan('tenant:create', ['name' => 'X', 'subdomain' => 'admin', '--db-name' => 'bustotal_test_b'] + $base)->assertFailed();
        $this->artisan('tenant:create', ['name' => 'X', 'subdomain' => 'a', '--db-name' => 'bustotal_test_b'] + $base)->assertFailed();
        $this->artisan('tenant:create', ['name' => 'X', 'subdomain' => 'Ungültig!', '--db-name' => 'bustotal_test_b'] + $base)->assertFailed();

        // dieselbe Datenbank wie eine andere Firma, oder die zentrale Datenbank
        $this->artisan('tenant:create', ['name' => 'X', 'subdomain' => 'b', '--db-name' => 'bustotal_test_a'] + $base)->assertFailed();
        $this->artisan('tenant:create', ['name' => 'X', 'subdomain' => 'b', '--db-name' => 'bustotal_test_central'] + $base)->assertFailed();

        // Datenbank nicht leer (Vertipper-Schutz): Die Datenbank von A ist belegt, ein zweiter Zugriff nach Umbenennung zählt auch.
        DB::connection('mysql')->getPdo()->exec('CREATE DATABASE `bustotal_test_c`');
        DB::connection('mysql')->getPdo()->exec('CREATE TABLE `bustotal_test_c`.`fremd` (id INT)');
        $this->artisan('tenant:create', ['name' => 'X', 'subdomain' => 'c', '--db-name' => 'bustotal_test_c'] + $base)->assertFailed();

        // falsche Zugangsdaten
        $this->artisan('tenant:create', ['name' => 'X', 'subdomain' => 'b', '--db-name' => 'bustotal_test_b', '--db-user' => $user, '--db-password' => 'falsch-'.$pw.'x'])
            ->assertFailed();

        $this->assertSame(1, Tenant::count());
    }

    public function test_tenant_migrate_baut_alle_firmen_auf(): void
    {
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $b = $this->makeTenant('b', 'bustotal_test_b');
        $b->update(['is_active' => false]);

        $this->artisan('tenant:migrate')->assertSuccessful();

        $this->assertNotNull($a->fresh()->migrated_at);
    }

    public function test_ohne_mandant_gibt_es_keine_kundentabellen_in_der_zentralen_datenbank(): void
    {
        // Die zentrale Datenbank enthält nur Mandanten. Ein vergessener Mandant fällt so sofort auf.
        $tables = collect(DB::connection('central')->select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0]);

        $this->assertTrue($tables->contains('tenants'));
        $this->assertFalse($tables->contains('customers'));
        $this->assertFalse($tables->contains('users'));
    }
}
