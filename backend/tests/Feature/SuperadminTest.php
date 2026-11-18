<?php

namespace Tests\Feature;

use App\Models\Superadmin;
use App\Models\SuperadminAuditLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\UsesTenantDatabases;
use Tests\TestCase;

/** Betreiber-Oberfläche: eigene Anmeldung, Firmen anlegen und sperren, strikt getrennt von den Firmen. */
class SuperadminTest extends TestCase
{
    use UsesTenantDatabases;

    private const ADMIN = 'http://admin.localhost/api/superadmin';

    private const PASSWORD = 'Betreiber-Pass-123';

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

    private function superadmin(array $override = []): Superadmin
    {
        return Superadmin::create($override + ['name' => 'Betreiber', 'email' => 'chef@betreiber.test', 'password' => self::PASSWORD]);
    }

    /** Meldet den Betreiber an und gibt das Token zurück. */
    private function token(string $email = 'chef@betreiber.test'): string
    {
        return $this->postJson(self::ADMIN.'/login', ['email' => $email, 'password' => self::PASSWORD])->assertOk()->json('token');
    }

    private function newTenantData(array $override = []): array
    {
        return $override + $this->dbCredentials() + [
            'name' => 'Neue Reisen GmbH', 'subdomain' => 'neu', 'db_database' => 'bustotal_test_b',
            'admin_email' => 'chef@neu.test', 'admin_name' => 'Chef',
        ];
    }

    public function test_anmeldung_und_eigene_daten(): void
    {
        $this->superadmin();

        $this->postJson(self::ADMIN.'/login', ['email' => 'chef@betreiber.test', 'password' => 'falsch'])->assertStatus(422);
        $this->postJson(self::ADMIN.'/login', ['email' => 'niemand@betreiber.test', 'password' => self::PASSWORD])->assertStatus(422);

        $token = $this->token();
        $this->withToken($token)->getJson(self::ADMIN.'/me')->assertOk()->assertJsonPath('email', 'chef@betreiber.test')
            ->assertJsonMissingPath('password');

        $this->withToken($token)->postJson(self::ADMIN.'/logout')->assertOk();
        $this->withToken($token)->getJson(self::ADMIN.'/me')->assertUnauthorized();
    }

    public function test_gesperrtes_konto_kommt_nicht_hinein(): void
    {
        $this->superadmin(['active' => false]);

        $this->postJson(self::ADMIN.'/login', ['email' => 'chef@betreiber.test', 'password' => self::PASSWORD])->assertStatus(422);
    }

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->getJson(self::ADMIN.'/tenants')->assertUnauthorized();
        $this->postJson(self::ADMIN.'/tenants', $this->newTenantData())->assertUnauthorized();
    }

    public function test_betreiber_schnittstelle_gibt_es_nur_an_der_betreiber_adresse(): void
    {
        $this->superadmin();
        $this->makeTenant('a', 'bustotal_test_a');

        // An einer Firmenadresse oder ohne Unterdomäne: nicht vorhanden.
        $this->postJson('http://a.localhost/api/superadmin/login', ['email' => 'chef@betreiber.test', 'password' => self::PASSWORD])->assertNotFound();
        $this->postJson('http://localhost/api/superadmin/login', ['email' => 'chef@betreiber.test', 'password' => self::PASSWORD])->assertNotFound();

        // Umgekehrt: An der Betreiber-Adresse gibt es keine Firmen-Schnittstelle.
        $this->getJson('http://admin.localhost/api/branding')->assertNotFound();
        $this->postJson('http://admin.localhost/api/login', ['email' => 'admin@a.test', 'password' => 'geheim-12345'])->assertNotFound();
    }

    public function test_tokens_gelten_nicht_ueber_die_grenze(): void
    {
        $this->superadmin();
        $this->makeTenant('a', 'bustotal_test_a');

        $superToken = $this->token();
        $tenantToken = $this->postJson('http://a.localhost/api/login', ['email' => 'admin@a.test', 'password' => 'geheim-12345'])->json('token');

        // Das Betreiber-Token öffnet keine Firma, das Firmen-Token keine Betreiber-Seite.
        $this->withToken($superToken)->getJson('http://a.localhost/api/me')->assertUnauthorized();
        $this->withToken($tenantToken)->getJson(self::ADMIN.'/tenants')->assertUnauthorized();
        $this->withToken($tenantToken)->getJson('http://a.localhost/api/me')->assertOk();
    }

    public function test_vorlaeufiges_passwort_muss_zuerst_geaendert_werden(): void
    {
        $this->superadmin(['must_change_password' => true]);
        $token = $this->token();

        $this->withToken($token)->getJson(self::ADMIN.'/tenants')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->withToken($token)->getJson(self::ADMIN.'/me')->assertOk()->assertJsonPath('must_change_password', true);

        $this->withToken($token)->postJson(self::ADMIN.'/me/password', [
            'current_password' => self::PASSWORD, 'password' => 'kurz1', 'password_confirmation' => 'kurz1',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->withToken($token)->postJson(self::ADMIN.'/me/password', [
            'current_password' => self::PASSWORD, 'password' => 'Neues-Passwort-456', 'password_confirmation' => 'Neues-Passwort-456',
        ])->assertOk();

        $this->withToken($token)->getJson(self::ADMIN.'/tenants')->assertOk();
    }

    public function test_firma_anlegen_ueber_die_oberflaeche(): void
    {
        $this->superadmin();
        $token = $this->token();

        $response = $this->withToken($token)->postJson(self::ADMIN.'/tenants', $this->newTenantData())->assertCreated();

        $response->assertJsonPath('subdomain', 'neu')->assertJsonPath('host', 'neu.localhost')->assertJsonPath('is_active', true);
        $temporary = $response->json('temporary_password');
        $this->assertNotEmpty($temporary);
        // Das Datenbank-Passwort kommt nie zurück.
        $this->assertStringNotContainsString('db_password', $response->getContent());

        // Die neue Firma ist sofort benutzbar: Der erste Administrator muss das vorläufige Passwort ändern.
        $login = $this->postJson('http://neu.localhost/api/login', ['email' => 'chef@neu.test', 'password' => $temporary])->assertOk();
        $this->assertTrue($login->json('user.must_change_password'));
        $this->getJson('http://neu.localhost/api/branding')->assertOk()->assertJsonPath('name', 'Neue Reisen GmbH');

        // Danach arbeitet die Betreiber-Oberfläche weiter in der zentralen Datenbank.
        $this->withToken($token)->getJson(self::ADMIN.'/tenants')->assertOk()->assertJsonCount(1);
        $this->assertSame(1, SuperadminAuditLog::where('event', 'tenant_created')->count());
    }

    public function test_firma_anlegen_lehnt_unsichere_angaben_ab(): void
    {
        $this->superadmin();
        $this->makeTenant('a', 'bustotal_test_a');
        $token = $this->token();
        $post = fn (array $o) => $this->withToken($token)->postJson(self::ADMIN.'/tenants', $this->newTenantData($o));

        $post(['subdomain' => 'admin'])->assertStatus(422);                            // reserviert
        $post(['subdomain' => 'a'])->assertStatus(422);                                // schon vergeben
        $post(['subdomain' => 'Ungültig!'])->assertStatus(422);                        // Schreibweise
        $post(['db_database' => 'bustotal_test_a'])->assertStatus(422);                // Datenbank gehört einer anderen Firma
        $post(['db_database' => 'bustotal_test_central'])->assertStatus(422);          // zentrale Datenbank
        $post(['db_database' => 'x`; DROP DATABASE y; --'])->assertStatus(422)->assertJsonValidationErrors('db_database');
        $post(['db_password' => 'falsches-passwort-xyz'])->assertStatus(422);          // Verbindung schlägt fehl
        $post(['admin_email' => 'keine-mail'])->assertStatus(422)->assertJsonValidationErrors('admin_email');

        $this->assertSame(1, Tenant::count());
    }

    public function test_sperren_und_entsperren(): void
    {
        $this->superadmin();
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $token = $this->token();
        $payload = fn (bool $active) => [
            'name' => $a->name, 'is_active' => $active, 'db_host' => $a->db_host, 'db_port' => $a->db_port, 'db_username' => $a->db_username,
        ];

        $this->getJson('http://a.localhost/api/branding')->assertOk();

        $this->withToken($token)->putJson(self::ADMIN.'/tenants/'.$a->id, $payload(false))->assertOk()->assertJsonPath('is_active', false);
        $this->getJson('http://a.localhost/api/branding')->assertNotFound();

        $this->withToken($token)->putJson(self::ADMIN.'/tenants/'.$a->id, $payload(true))->assertOk();
        $this->getJson('http://a.localhost/api/branding')->assertOk();

        $this->assertSame(['tenant_locked', 'tenant_unlocked'], SuperadminAuditLog::whereIn('event', ['tenant_locked', 'tenant_unlocked'])->orderBy('id')->pluck('event')->all());
    }

    public function test_falsche_zugangsdaten_werden_nicht_gespeichert(): void
    {
        $this->superadmin();
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $token = $this->token();

        $this->withToken($token)->putJson(self::ADMIN.'/tenants/'.$a->id, [
            'name' => 'Anders', 'is_active' => true, 'db_host' => $a->db_host, 'db_port' => $a->db_port,
            'db_username' => $a->db_username, 'db_password' => 'falsch-falsch-falsch',
        ])->assertStatus(422);

        $this->assertSame('Firma A', $a->fresh()->name);
        $this->getJson('http://a.localhost/api/branding')->assertOk();
    }

    public function test_passwort_leer_lassen_behaelt_das_alte(): void
    {
        $this->superadmin();
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $token = $this->token();

        $this->withToken($token)->putJson(self::ADMIN.'/tenants/'.$a->id, [
            'name' => 'Umbenannt', 'is_active' => true, 'db_host' => $a->db_host, 'db_port' => $a->db_port,
            'db_username' => $a->db_username, 'db_password' => '',
        ])->assertOk()->assertJsonPath('name', 'Umbenannt');

        $this->getJson('http://a.localhost/api/branding')->assertOk();
    }

    public function test_datenbank_aktualisieren_und_administrator_nachlegen(): void
    {
        $this->superadmin();
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $token = $this->token();

        $this->withToken($token)->postJson(self::ADMIN.'/tenants/'.$a->id.'/migrate')->assertOk();

        $new = $this->withToken($token)->postJson(self::ADMIN.'/tenants/'.$a->id.'/admin', ['email' => 'neu@a.test'])->assertCreated();
        $this->postJson('http://a.localhost/api/login', ['email' => 'neu@a.test', 'password' => $new->json('temporary_password')])->assertOk();

        // vorhandene Adresse: abgelehnt
        $this->withToken($token)->postJson(self::ADMIN.'/tenants/'.$a->id.'/admin', ['email' => 'admin@a.test'])->assertStatus(422);
    }

    public function test_vorlaeufiges_passwort_ist_einsehbar_bis_der_administrator_es_aendert(): void
    {
        $this->superadmin();
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $token = $this->token();

        $new = $this->withToken($token)->postJson(self::ADMIN.'/tenants/'.$a->id.'/admin', ['email' => 'neu@a.test'])->assertCreated();
        $temporary = $new->json('temporary_password');

        $admins = collect($this->withToken($token)->getJson(self::ADMIN.'/tenants/'.$a->id.'/admins')->assertOk()->json());
        $neu = $admins->firstWhere('email', 'neu@a.test');
        $this->assertTrue($neu['has_temporary']);
        $this->assertStringNotContainsString($temporary, json_encode($admins)); // die Liste enthält nie Passwörter
        $this->assertFalse($admins->firstWhere('email', 'admin@a.test')['has_temporary']);

        // Beliebig oft einsehbar, jedes Mal protokolliert
        for ($i = 0; $i < 2; $i++) {
            $this->withToken($token)->postJson(self::ADMIN.'/tenants/'.$a->id.'/admins/'.$neu['id'].'/password')
                ->assertOk()->assertJson(['email' => 'neu@a.test', 'temporary_password' => $temporary]);
        }
        $this->assertSame(2, SuperadminAuditLog::where('event', 'tenant_admin_password_shown')->count());

        // In der Datenbank der Firma steht es verschlüsselt, nicht im Klartext
        $raw = $this->inTenant($a, fn () => DB::table('users')->where('email', 'neu@a.test')->value('temporary_password'));
        $this->assertNotSame($temporary, $raw);

        // Mit dem Passwort anmelden und ein eigenes vergeben: danach ist nichts mehr aufbewahrt
        $login = $this->postJson('http://a.localhost/api/login', ['email' => 'neu@a.test', 'password' => $temporary])->assertOk();
        $this->withToken($login->json('token'))->postJson('http://a.localhost/api/me/password', [
            'current_password' => $temporary, 'password' => 'Eigenes-Passwort-42', 'password_confirmation' => 'Eigenes-Passwort-42',
        ])->assertOk();

        $this->withToken($token)->postJson(self::ADMIN.'/tenants/'.$a->id.'/admins/'.$neu['id'].'/password')->assertStatus(422);
        $raw = $this->inTenant($a, fn () => DB::table('users')->where('email', 'neu@a.test')->value('temporary_password'));
        $this->assertNull($raw);
    }

    public function test_neues_vorlaeufiges_passwort_vergeben(): void
    {
        $this->superadmin();
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $token = $this->token();
        $adminId = collect($this->withToken($token)->getJson(self::ADMIN.'/tenants/'.$a->id.'/admins')->json())->firstWhere('email', 'admin@a.test')['id'];

        // Ein alter Login (Token) wird ungültig
        $old = $this->postJson('http://a.localhost/api/login', ['email' => 'admin@a.test', 'password' => 'geheim-12345'])->assertOk()->json('token');

        $reset = $this->withToken($token)->postJson(self::ADMIN.'/tenants/'.$a->id.'/admins/'.$adminId.'/password/reset')->assertOk();
        $temporary = $reset->json('temporary_password');

        $this->postJson('http://a.localhost/api/login', ['email' => 'admin@a.test', 'password' => 'geheim-12345'])->assertStatus(422);
        $this->postJson('http://a.localhost/api/login', ['email' => 'admin@a.test', 'password' => $temporary])->assertOk()
            ->assertJsonPath('user.must_change_password', true);
        $this->app['auth']->forgetGuards();
        $this->withToken($old)->getJson('http://a.localhost/api/me')->assertUnauthorized();

        // Danach einsehbar, und das Betreiber-Protokoll kennt den Vorgang (ohne Passwort)
        $this->withToken($token)->postJson(self::ADMIN.'/tenants/'.$a->id.'/admins/'.$adminId.'/password')->assertOk()->assertJson(['temporary_password' => $temporary]);
        $entry = SuperadminAuditLog::where('event', 'tenant_admin_password_reset')->firstOrFail();
        $this->assertStringNotContainsString($temporary, json_encode($entry->details));
    }

    public function test_passwort_funktionen_gelten_nur_fuer_administratoren_der_firma(): void
    {
        $this->superadmin();
        $a = $this->makeTenant('a', 'bustotal_test_a');
        $token = $this->token();

        $this->withToken($token)->postJson(self::ADMIN.'/tenants/'.$a->id.'/admins/gibt-es-nicht/password/reset')->assertStatus(422);
        $this->withToken($token)->postJson(self::ADMIN.'/tenants/'.$a->id.'/admins/gibt-es-nicht/password')->assertStatus(422);
        // ohne Betreiber-Anmeldung nichts
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson(self::ADMIN.'/tenants/'.$a->id.'/admins')->assertUnauthorized();
    }

    public function test_es_gibt_keine_moeglichkeit_eine_firma_zu_loeschen(): void
    {
        $this->superadmin();
        $a = $this->makeTenant('a', 'bustotal_test_a');

        $this->withToken($this->token())->deleteJson(self::ADMIN.'/tenants/'.$a->id)->assertStatus(405);
        $this->assertSame(1, Tenant::count());
    }

    public function test_betreiber_protokoll_ist_unveraenderlich(): void
    {
        $this->superadmin();
        $this->token();

        $entry = SuperadminAuditLog::where('event', 'login')->firstOrFail();

        $this->expectException(LogicException::class);
        $entry->update(['event' => 'manipuliert']);
    }

    public function test_betreiber_protokoll_kann_nicht_geloescht_werden(): void
    {
        $this->superadmin();
        $this->token();

        $this->expectException(LogicException::class);
        SuperadminAuditLog::where('event', 'login')->firstOrFail()->delete();
    }

    public function test_befehl_legt_betreiber_konto_an(): void
    {
        $this->artisan('superadmin:anlegen', ['email' => 'Neu@Betreiber.test', 'name' => 'Neu'])->assertSuccessful();

        $admin = Superadmin::where('email', 'neu@betreiber.test')->firstOrFail();
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue($admin->active);

        // doppelte Adresse
        $this->artisan('superadmin:anlegen', ['email' => 'neu@betreiber.test'])->assertFailed();
        $this->assertSame(1, DB::connection('central')->table('superadmins')->count());
    }
}
