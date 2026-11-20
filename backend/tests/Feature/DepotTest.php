<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Depot;
use App\Models\Place;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Betriebshöfe und die zentrale Ortstabelle. Der Adressdienst wird nie wirklich angerufen (Http::fake). Der Kern: Koordinaten werden
 * gespeichert, und eine bekannte Adresse kostet keine Anfrage mehr.
 */
class DepotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
        config(['services.openrouteservice.key' => 'test-key']);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    /** Der Dienst findet zu jeder Anfrage einen Ort; die Koordinaten hängen am Text, damit man Orte unterscheiden kann. */
    private function geocoder(): void
    {
        Http::fake(['*/geocode/search*' => fn ($request) => Http::response(['features' => [[
            'geometry' => ['coordinates' => [10.0 + strlen($request['text']) / 1000, 49.0]],
            'properties' => ['label' => 'Gefunden: '.$request['text'], 'postalcode' => '91522', 'locality' => 'Ansbach', 'country_a' => 'DEU'],
        ]]])]);
    }

    private function body(array $over = []): array
    {
        return array_merge(['name' => 'Ansbach', 'address' => 'Hauptstraße 5, 91522 Ansbach'], $over);
    }

    // ---------- Rechte ----------

    public function test_lesen_duerfen_alle_ausser_fahrer_aendern_nur_admin(): void
    {
        $this->geocoder();
        foreach ([Role::Admin, Role::Disponent, Role::Buchhaltung] as $role) {
            $this->as($role)->getJson('/api/depots')->assertOk();
        }
        $this->as(Role::Fahrer)->getJson('/api/depots')->assertForbidden();

        foreach ([Role::Disponent, Role::Buchhaltung, Role::Fahrer] as $role) {
            $this->as($role)->postJson('/api/depots', $this->body())->assertForbidden();
        }
        $this->as(Role::Admin)->postJson('/api/depots', $this->body())->assertCreated();
        $this->assertDatabaseCount('depots', 1);

        $id = Depot::first()->id;
        $this->as(Role::Disponent)->putJson("/api/depots/$id", $this->body(['name' => 'X']))->assertForbidden();
        $this->as(Role::Disponent)->deleteJson("/api/depots/$id")->assertForbidden();
        $this->getJson('/api/depots')->assertOk();
    }

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->getJson('/api/depots')->assertUnauthorized();
    }

    // ---------- Koordinaten werden gespeichert ----------

    public function test_betriebshof_speichert_den_ort_mit_koordinaten(): void
    {
        $this->geocoder();

        $this->as(Role::Admin)->postJson('/api/depots', $this->body())->assertCreated()
            ->assertJsonPath('name', 'Ansbach')
            ->assertJsonPath('is_default', true)
            ->assertJsonPath('place.label', 'Gefunden: Hauptstraße 5, 91522 Ansbach')
            ->assertJsonPath('place.lat', 49)
            ->assertJsonPath('place.city', 'Ansbach')
            ->assertJsonPath('place.source', 'openrouteservice');

        $place = Place::firstOrFail();
        $this->assertNotNull($place->geocoded_at);
        $this->assertSame($place->id, Depot::first()->place_id);
        Http::assertSentCount(1);
    }

    public function test_die_liste_liefert_die_koordinaten_ohne_weitere_anfrage(): void
    {
        $this->geocoder();
        $this->as(Role::Admin)->postJson('/api/depots', $this->body())->assertCreated();
        Http::fake();   // ab hier darf kein Dienst mehr angerufen werden

        $this->as(Role::Disponent)->getJson('/api/depots')->assertOk()->assertJsonPath('0.place.lat', 49);
        Http::assertNothingSent();
    }

    // ---------- Keine neue Anfrage für bekannte Adressen ----------

    public function test_gleiche_adresse_wird_nur_einmal_gesucht(): void
    {
        $this->geocoder();

        $this->as(Role::Admin)->postJson('/api/depots', $this->body())->assertCreated();
        // andere Schreibweise (Groß-/Kleinschreibung, Leerzeichen) und anderer Betriebshof: derselbe Ort
        $this->as(Role::Admin)->postJson('/api/depots', $this->body(['name' => 'Zweiter', 'address' => '  hauptstraße 5,  91522   ANSBACH ']))->assertCreated();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('places', 1);
        $this->assertSame(1, Depot::distinct('place_id')->count('place_id'));
    }

    public function test_aendern_ohne_neue_adresse_fragt_den_dienst_nicht(): void
    {
        $this->geocoder();
        $id = $this->as(Role::Admin)->postJson('/api/depots', $this->body())->json('id');

        $this->as(Role::Admin)->putJson("/api/depots/$id", $this->body(['name' => 'Neuer Name']))->assertOk()->assertJsonPath('name', 'Neuer Name');

        Http::assertSentCount(1);
    }

    public function test_neue_adresse_bekommt_einen_neuen_ort(): void
    {
        $this->geocoder();
        $id = $this->as(Role::Admin)->postJson('/api/depots', $this->body())->json('id');

        $res = $this->as(Role::Admin)->putJson("/api/depots/$id", $this->body(['address' => 'Bahnhofstraße 1, 91522 Ansbach']))->assertOk();

        $res->assertJsonPath('place.label', 'Gefunden: Bahnhofstraße 1, 91522 Ansbach');
        Http::assertSentCount(2);
        $this->assertDatabaseCount('places', 2);   // der alte Ort bleibt, andere Datensätze können ihn verwenden
    }

    // ---------- Fehler ----------

    public function test_unbekannte_adresse_wird_abgelehnt_und_nichts_gespeichert(): void
    {
        Http::fake(['*/geocode/search*' => Http::response(['features' => []])]);

        $this->as(Role::Admin)->postJson('/api/depots', $this->body(['address' => 'Nirgendwo 99']))
            ->assertUnprocessable()->assertJsonValidationErrors(['address']);

        $this->assertDatabaseCount('depots', 0);
        $this->assertDatabaseCount('places', 0);
    }

    public function test_dienst_nicht_eingerichtet_ist_ein_klarer_fehler_ohne_speichern(): void
    {
        config(['services.openrouteservice.key' => null]);

        $this->as(Role::Admin)->postJson('/api/depots', $this->body())->assertStatus(424)->assertJsonPath('reason', 'not_configured');

        $this->assertDatabaseCount('depots', 0);
    }

    public function test_pflichtfelder(): void
    {
        $this->as(Role::Admin)->postJson('/api/depots', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'address']);
    }

    public function test_der_browser_kann_den_ort_nicht_vorgeben(): void
    {
        $this->geocoder();
        $fremd = Place::create(['lookup_key' => sha1('x'), 'query' => 'x', 'label' => 'Fremd', 'lat' => 1, 'lon' => 1, 'source' => 'test', 'geocoded_at' => now()]);

        $this->as(Role::Admin)->postJson('/api/depots', $this->body() + ['place_id' => $fremd->id, 'lat' => 1])->assertCreated();

        $this->assertNotSame($fremd->id, Depot::first()->place_id);
    }

    // ---------- Standard-Betriebshof ----------

    public function test_nur_ein_betriebshof_ist_standard(): void
    {
        $this->geocoder();
        $first = $this->as(Role::Admin)->postJson('/api/depots', $this->body())->json('id');
        $second = $this->as(Role::Admin)->postJson('/api/depots', $this->body(['name' => 'B', 'address' => 'Anderswo 2, Ulm']))->assertJsonPath('is_default', false)->json('id');

        $this->as(Role::Admin)->putJson("/api/depots/$second", ['name' => 'B', 'address' => 'Anderswo 2, Ulm', 'is_default' => true])->assertOk()->assertJsonPath('is_default', true);

        $this->assertFalse(Depot::find($first)->is_default);
        $this->assertSame(1, Depot::where('is_default', true)->count());
        // der Standard steht in der Liste oben
        $this->as(Role::Admin)->getJson('/api/depots')->assertJsonPath('0.id', $second);
    }

    public function test_loeschen_des_standards_macht_den_naechsten_zum_standard(): void
    {
        $this->geocoder();
        $a = $this->as(Role::Admin)->postJson('/api/depots', $this->body(['name' => 'A']))->json('id');
        $this->as(Role::Admin)->postJson('/api/depots', $this->body(['name' => 'B', 'address' => 'Anderswo 2, Ulm']));

        $this->as(Role::Admin)->deleteJson("/api/depots/$a")->assertNoContent();

        $this->assertSame(['B'], Depot::where('is_default', true)->pluck('name')->all());
        $this->assertDatabaseCount('places', 2);   // Orte bleiben erhalten
    }
}
