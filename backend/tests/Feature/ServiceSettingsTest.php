<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Einstellungen › Dienste: eigener Schlüssel je Firma (verschlüsselt), allgemeiner Schlüssel nur als Rückfall. */
class ServiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const OWN = 'eigener-schluessel-a3f9';

    private const SHARED = 'allgemeiner-schluessel-77';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
        config(['services.openrouteservice.key' => self::SHARED]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function state(): array
    {
        return $this->as(Role::Admin)->getJson('/api/settings/services')->assertOk()->json('data.0');
    }

    public function test_nur_administratoren(): void
    {
        $this->getJson('/api/settings/services')->assertUnauthorized();
        foreach ([Role::Disponent, Role::Buchhaltung, Role::Fahrer] as $role) {
            $this->as($role)->getJson('/api/settings/services')->assertForbidden();
            $this->as($role)->putJson('/api/settings/services/openrouteservice', ['api_key' => self::OWN])->assertForbidden();
        }
        $this->as(Role::Admin)->putJson('/api/settings/services/unbekannt', ['api_key' => self::OWN])->assertNotFound();
    }

    public function test_ohne_eigenen_schluessel_gilt_der_allgemeine_als_rueckfall(): void
    {
        $this->assertSame(['service' => 'openrouteservice', 'source' => 'shared', 'own_hint' => null, 'shared_available' => true], $this->state());

        config(['services.openrouteservice.key' => null]);
        $this->assertSame('none', $this->state()['source']);
    }

    public function test_eigener_schluessel_wird_verschluesselt_gespeichert_und_nie_ausgegeben(): void
    {
        $res = $this->as(Role::Admin)->putJson('/api/settings/services/openrouteservice', ['api_key' => '  '.self::OWN.' '])->assertOk();

        $this->assertSame('own', $res->json('source'));
        $this->assertSame('…a3f9', $res->json('own_hint'));
        $this->assertStringNotContainsString(self::OWN, $res->getContent());
        $this->assertStringNotContainsString(self::OWN, json_encode($this->state()));

        // in der Datenbank verschlüsselt, im Protokoll ohne Schlüssel
        $raw = DB::table('service_credentials')->where('service', 'openrouteservice')->value('api_key');
        $this->assertNotSame(self::OWN, $raw);
        $this->assertStringNotContainsString(self::OWN, $raw);
        $this->assertStringNotContainsString('a3f9', json_encode(AuditLog::all()->toArray()));
        $this->assertTrue(AuditLog::where('event', 'service_key_set')->exists());
    }

    public function test_schluessel_wird_geprueft(): void
    {
        $this->as(Role::Admin)->putJson('/api/settings/services/openrouteservice', [])->assertUnprocessable()->assertJsonValidationErrors('api_key');
        $this->as(Role::Admin)->putJson('/api/settings/services/openrouteservice', ['api_key' => 'kurz'])->assertUnprocessable();
    }

    public function test_der_dienst_nutzt_den_eigenen_schluessel_sonst_den_allgemeinen(): void
    {
        Http::fake(['*' => Http::response(['features' => []])]);

        $this->as(Role::Disponent)->getJson('/api/geocode?q=Ulm')->assertOk();
        Http::assertSent(fn (Request $r) => $r->header('Authorization') === [self::SHARED]);

        $this->as(Role::Admin)->putJson('/api/settings/services/openrouteservice', ['api_key' => self::OWN])->assertOk();
        $this->as(Role::Disponent)->getJson('/api/geocode?q=Biberach')->assertOk();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'Biberach') && $r->header('Authorization') === [self::OWN]);

        // Entfernen: wieder der allgemeine Schlüssel
        $this->as(Role::Admin)->deleteJson('/api/settings/services/openrouteservice')->assertOk()->assertJsonPath('source', 'shared');
        $this->as(Role::Disponent)->getJson('/api/geocode?q=Memmingen')->assertOk();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'Memmingen') && $r->header('Authorization') === [self::SHARED]);
        $this->assertTrue(AuditLog::where('event', 'service_key_removed')->exists());
    }

    public function test_verbindung_pruefen_meldet_erfolg_oder_ursache(): void
    {
        Http::fake(['*' => Http::response(['features' => []])]);
        $this->as(Role::Admin)->postJson('/api/settings/services/openrouteservice/test')
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('source', 'shared');

        // Der allgemeine Schlüssel wird abgelehnt: Der Betreiber muss handeln, die Firma kann einen eigenen eintragen.
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['error' => 'Access denied'], 401)]);
        $res = $this->as(Role::Admin)->postJson('/api/settings/services/openrouteservice/test')->assertOk()->assertJsonPath('ok', false)->assertJsonPath('reason', 'auth');
        $this->assertStringContainsString('allgemeine Zugangsschlüssel', $res->json('message'));
        $this->assertStringContainsString('R401', $res->json('message'));

        // Der eigene Schlüssel wird abgelehnt: Die Firma prüft ihn selbst.
        $this->as(Role::Admin)->putJson('/api/settings/services/openrouteservice', ['api_key' => self::OWN])->assertOk();
        $res = $this->as(Role::Admin)->postJson('/api/settings/services/openrouteservice/test')->assertOk()->assertJsonPath('ok', false);
        $this->assertStringContainsString('Ihrer Firma', $res->json('message'));
        $this->assertStringContainsString('Einstellungen › Dienste', $res->json('message'));
    }

    public function test_die_pruefung_umgeht_den_zwischenspeicher(): void
    {
        Http::fake(['*' => Http::response(['features' => []])]);
        $this->as(Role::Admin)->postJson('/api/settings/services/openrouteservice/test')->assertOk();
        $this->as(Role::Admin)->postJson('/api/settings/services/openrouteservice/test')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_ein_neuer_schluessel_hebt_die_kontingentsperre_auf(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Quota exceeded'], 403)]);
        $this->as(Role::Disponent)->getJson('/api/geocode?q=Ulm')->assertStatus(424)->assertJsonPath('reason', 'limit');
        $this->as(Role::Disponent)->getJson('/api/geocode?q=Biberach')->assertStatus(424);
        Http::assertSentCount(1);   // gesperrt: Die zweite Suche geht nicht mehr hinaus

        $this->as(Role::Admin)->putJson('/api/settings/services/openrouteservice', ['api_key' => self::OWN])->assertOk();
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['features' => []])]);
        $this->as(Role::Disponent)->getJson('/api/geocode?q=Memmingen')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_ohne_jeden_schluessel_nennt_die_meldung_die_einstellung(): void
    {
        config(['services.openrouteservice.key' => null]);
        Http::fake();
        $this->as(Role::Disponent)->getJson('/api/geocode?q=Ulm')->assertStatus(424)->assertJsonPath('reason', 'not_configured')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Einstellungen › Dienste'));
    }
}
