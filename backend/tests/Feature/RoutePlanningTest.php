<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Routing\OpenRouteService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Streckenplanung: Adresssuche und Tourberechnung. Der Routing-Dienst wird NIE wirklich angerufen (Http::fake),
 * die Tests brauchen weder Zugang noch Netz.
 */
class RoutePlanningTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-secret-key';

    private const DEPOT = ['lat' => 49.30, 'lon' => 10.57, 'label' => 'Betriebshof Ansbach'];

    private const A = ['lat' => 48.14, 'lon' => 11.58, 'label' => 'München Hbf', 'dwell_minutes' => 30];

    private const B = ['lat' => 47.66, 'lon' => 9.48, 'label' => 'Friedrichshafen'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
        config(['services.openrouteservice.key' => self::KEY, 'services.openrouteservice.duration_factor' => 1.0]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    /** Antwort des Routing-Dienstes mit je einem Abschnitt pro (Strecke in km, Fahrzeit in Stunden). */
    private function fakeRoute(array $segments, array $coordinates = [[10.57, 49.30], [11.58, 48.14]]): void
    {
        $distance = array_sum(array_column($segments, 0)) * 1000;
        $duration = array_sum(array_column($segments, 1)) * 3600;

        Http::fake(['*/v2/directions/driving-hgv/geojson' => Http::response(['features' => [[
            'properties' => [
                'summary' => ['distance' => $distance, 'duration' => $duration],
                'segments' => array_map(fn ($s) => ['distance' => $s[0] * 1000, 'duration' => $s[1] * 3600], $segments),
            ],
            'geometry' => ['coordinates' => $coordinates],
        ]]])]);
    }

    private function payload(array $extra = []): array
    {
        return ['stops' => [self::A, self::B]] + $extra;
    }

    // ---------- Rechte ----------

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->getJson('/api/geocode?q=Ulm')->assertUnauthorized();
        $this->postJson('/api/routes/calculate', $this->payload())->assertUnauthorized();
    }

    public function test_nur_admin_und_disponent_duerfen_planen(): void
    {
        foreach ([Role::Admin, Role::Disponent] as $role) {
            $this->fakeRoute([[200, 2.5]]);
            $this->as($role)->postJson('/api/routes/calculate', $this->payload())->assertOk();
        }
        foreach ([Role::Buchhaltung, Role::Fahrer] as $role) {
            $this->as($role)->postJson('/api/routes/calculate', $this->payload())->assertForbidden();
            $this->as($role)->getJson('/api/geocode?q=Ulm')->assertForbidden();
        }
    }

    // ---------- Adresssuche ----------

    public function test_adresssuche_liefert_treffer_mit_koordinaten(): void
    {
        Http::fake(['*/geocode/search*' => Http::response(['features' => [
            ['geometry' => ['coordinates' => [9.99, 48.40]], 'properties' => ['label' => 'Ulm, Baden-Württemberg, Deutschland', 'postalcode' => '89073', 'locality' => 'Ulm', 'country_a' => 'DEU']],
            ['geometry' => ['coordinates' => [10.01, 48.42]], 'properties' => ['label' => 'Ulm Hbf']],
            ['geometry' => ['coordinates' => []], 'properties' => ['label' => 'kaputt']], // ohne Koordinaten: wird übersprungen
        ]])]);

        $hits = $this->as(Role::Disponent)->getJson('/api/geocode?q=Ulm')->assertOk()->json('data');

        $this->assertCount(2, $hits);
        $this->assertSame(['label' => 'Ulm, Baden-Württemberg, Deutschland', 'postal_code' => '89073', 'city' => 'Ulm', 'country' => 'DEU', 'lat' => 48.4, 'lon' => 9.99], $hits[0]);
    }

    public function test_der_zugangsschluessel_geht_nur_im_header_raus_nie_in_der_adresse(): void
    {
        Http::fake(['*/geocode/search*' => Http::response(['features' => []])]);

        $this->as(Role::Admin)->getJson('/api/geocode?q=Ulm')->assertOk();

        Http::assertSent(function (Request $r) {
            return $r->hasHeader('Authorization', self::KEY) && ! str_contains($r->url(), self::KEY) && ! str_contains($r->url(), 'api_key');
        });
    }

    public function test_adresssuche_prueft_die_eingabe(): void
    {
        Http::fake();

        $this->as(Role::Admin)->getJson('/api/geocode')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->as(Role::Admin)->getJson('/api/geocode?q=U')->assertUnprocessable();
        $this->as(Role::Admin)->getJson('/api/geocode?q=Ulm&limit=99')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_gleiche_suche_wird_zwischengespeichert(): void
    {
        Http::fake(['*/geocode/search*' => Http::response(['features' => []])]);

        $this->as(Role::Admin)->getJson('/api/geocode?q=Ulm')->assertOk();
        $this->as(Role::Admin)->getJson('/api/geocode?q=ulm')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_leerzeichen_und_satzzeichen_am_ende_loesen_keine_neue_suche_aus(): void
    {
        Http::fake(['*/geocode/search*' => Http::response(['features' => []])]);

        $user = User::factory()->create(['role' => Role::Admin]);
        foreach (['Ulm Hbf', 'ulm  hbf', ' Ulm Hbf,', 'Ulm Hbf.'] as $q) {
            $this->actingAs($user, 'sanctum')->getJson('/api/geocode?q='.urlencode($q))->assertOk();
        }

        Http::assertSentCount(1);
    }

    public function test_aufgebrauchtes_kontingent_sperrt_die_ortssuche_bis_mitternacht(): void
    {
        Http::fake(['*/geocode/search*' => Http::response(['error' => 'Quota exceeded'], 403)]);
        $user = User::factory()->create(['role' => Role::Disponent]);

        $this->actingAs($user, 'sanctum')->getJson('/api/geocode?q=Ulm')
            ->assertStatus(424)->assertJsonPath('reason', 'limit')->assertJsonPath('message', fn ($m) => str_contains($m, 'Tageskontingent'));
        // Weitere Suchen gehen nicht mehr an den Dienst (jede Absage würde sonst weiter zählen).
        $this->actingAs($user, 'sanctum')->getJson('/api/geocode?q=Biberach')->assertStatus(424)->assertJsonPath('reason', 'limit');
        Http::assertSentCount(1);
    }

    public function test_gesperrte_ortssuche_laesst_die_routenberechnung_weiterlaufen(): void
    {
        Http::fake([
            '*/geocode/search*' => Http::response(['error' => 'Quota exceeded'], 403),
            '*/v2/directions/driving-hgv/geojson' => Http::response(['features' => [[
                'properties' => ['summary' => ['distance' => 200000, 'duration' => 9000], 'segments' => [['distance' => 200000, 'duration' => 9000]]],
                'geometry' => ['coordinates' => [[10.57, 49.30], [11.58, 48.14]]],
            ]]]),
        ]);
        $user = User::factory()->create(['role' => Role::Disponent]);

        $this->actingAs($user, 'sanctum')->getJson('/api/geocode?q=Ulm')->assertStatus(424);
        $this->actingAs($user, 'sanctum')->postJson('/api/routes/calculate', $this->payload())->assertOk();
    }

    public function test_anfragen_an_den_dienst_werden_je_bereich_gezaehlt(): void
    {
        Http::fake(['*/geocode/search*' => Http::response(['features' => []])]);
        $user = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($user, 'sanctum')->getJson('/api/geocode?q=Ulm')->assertOk();
        $this->actingAs($user, 'sanctum')->getJson('/api/geocode?q=Ulm')->assertOk(); // aus dem Zwischenspeicher: zählt nicht
        $this->actingAs($user, 'sanctum')->getJson('/api/geocode?q=Biberach')->assertOk();

        $this->assertSame(['geocode' => 2, 'directions' => 0, 'matrix' => 0], OpenRouteService::usage());
    }

    public function test_ortssuche_ist_je_benutzer_begrenzt(): void
    {
        Http::fake(['*/geocode/search*' => Http::response(['features' => []])]);
        $user = User::factory()->create(['role' => Role::Admin]);

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user, 'sanctum')->getJson('/api/geocode?q=Ulm')->assertOk();
        }
        $this->actingAs($user, 'sanctum')->getJson('/api/geocode?q=Ulm')->assertStatus(429);
    }

    // ---------- Tourberechnung ----------

    public function test_tour_mit_betriebshof_trennt_leerfahrten_von_besetzter_strecke(): void
    {
        // Depot → München (Leerfahrt) → Friedrichshafen → Depot (Leerfahrt)
        $this->fakeRoute([[170, 2.0], [180, 2.0], [260, 3.0]]);

        $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload(['depot' => self::DEPOT]))->assertOk()->json();

        $this->assertSame(610000, $r['distance_m']);
        $this->assertSame(430000, $r['empty_distance_m']);
        $this->assertSame(180000, $r['loaded_distance_m']);
        $this->assertSame(7 * 3600, $r['drive_s']);
        $this->assertSame([true, false, true], array_column($r['legs'], 'empty'));
        $this->assertSame('Betriebshof Ansbach', $r['legs'][0]['from']['label']);
        $this->assertSame('München Hbf', $r['legs'][0]['to']['label']);
        $this->assertSame('Betriebshof Ansbach', $r['legs'][2]['to']['label']);
    }

    public function test_ohne_rueckfahrt_gibt_es_nur_eine_leerfahrt(): void
    {
        $this->fakeRoute([[170, 2.0], [180, 2.0]]);

        $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload(['depot' => self::DEPOT, 'return_to_depot' => false]))->assertOk()->json();

        $this->assertSame([true, false], array_column($r['legs'], 'empty'));
        $this->assertSame(170000, $r['empty_distance_m']);
    }

    public function test_ohne_betriebshof_gibt_es_keine_leerfahrten(): void
    {
        $this->fakeRoute([[400, 5.0]]);

        $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertOk()->json();

        $this->assertSame(0, $r['empty_distance_m']);
        $this->assertSame($r['distance_m'], $r['loaded_distance_m']);
    }

    public function test_pausen_und_ruhezeiten_verlaengern_die_tour(): void
    {
        // 10 Stunden reine Fahrzeit auf einem Abschnitt: 2 Pausen/Ruhezeit nach den Regeln
        $this->fakeRoute([[800, 10.0]]);

        $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertOk()->json();

        $this->assertSame(10 * 3600, $r['drive_s']);
        $this->assertSame(2, $r['days']);
        $this->assertGreaterThan(10 * 3600 + 11 * 3600, $r['elapsed_s']);
        $this->assertContains('rest', array_column($r['schedule'], 'type'));
    }

    public function test_abfahrtszeit_ergibt_zeitpunkte_im_ablauf(): void
    {
        $this->fakeRoute([[300, 6.0]]);

        $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload(['start_at' => '2027-03-01T06:00:00+01:00']))->assertOk()->json();

        $this->assertSame('2027-03-01T06:00:00+01:00', $r['start_at']);
        $this->assertSame('2027-03-01T06:00:00+01:00', $r['schedule'][0]['start_at']);
        // 30 Minuten Arbeit am ersten Halt (München) + 6 h Fahrt + 45 Minuten Pause
        $this->assertSame('2027-03-01T13:15:00+01:00', $r['end_at']);
    }

    public function test_halt_mit_aufenthalt_geht_in_den_ablauf_ein(): void
    {
        $this->fakeRoute([[200, 2.5]]);

        $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', ['stops' => [self::B, self::A + ['driver_free' => false]]])->assertOk()->json();

        $stop = collect($r['schedule'])->firstWhere('type', 'stop');
        $this->assertSame(1800, $stop['duration_s']);
        $this->assertSame(9000 + 1800, $r['elapsed_s']);
    }

    public function test_fahrzeugmasse_gehen_als_einschraenkung_an_den_dienst(): void
    {
        $this->fakeRoute([[200, 2.5]]);
        $bus = Vehicle::factory()->create(['height_m' => 3.7, 'width_m' => 2.55, 'length_m' => 13.0, 'gross_weight_kg' => 19500, 'cost_per_km' => 1.5]);

        $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload(['vehicle_id' => $bus->id]))->assertOk()->json();

        Http::assertSent(fn (Request $req) => $req['options']['profile_params']['restrictions'] === ['height' => 3.7, 'width' => 2.55, 'length' => 13.0, 'weight' => 19.5]);
        $this->assertSame($bus->id, $r['vehicle']['id']);
        $this->assertEquals(300.0, $r['vehicle_cost']); // 200 km × 1,50 €
    }

    public function test_ohne_fahrzeug_gibt_es_keine_einschraenkung_und_keine_kosten(): void
    {
        $this->fakeRoute([[200, 2.5]]);

        $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertOk()->json();

        Http::assertSent(fn (Request $req) => ! isset($req['options']));
        $this->assertNull($r['vehicle']);
        $this->assertNull($r['vehicle_cost']);
    }

    public function test_dauerfaktor_wird_auf_die_fahrzeit_angewendet(): void
    {
        config(['services.openrouteservice.duration_factor' => 0.9]);
        $this->fakeRoute([[100, 2.0]]);

        $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertOk()->json();

        $this->assertSame((int) ceil(7200 * 0.9), $r['drive_s']);
    }

    /**
     * Aus der Praxis: Mit einem Speicher, der wirklich serialisiert (Datei, Datenbank), gab die ZWEITE gleiche Suche einen
     * Serverfehler ("incomplete object"), weil Objekte im Speicher lagen. Der Test-Speicher "array" zeigt das nicht.
     */
    public function test_wiederholte_anfragen_funktionieren_mit_einem_serialisierenden_speicher(): void
    {
        $dir = sys_get_temp_dir().'/bustotal-cache-test-'.bin2hex(random_bytes(4));
        config(['cache.default' => 'file', 'cache.stores.file.path' => $dir]);
        Cache::forgetDriver();

        try {
            Http::fake(['*/geocode/search*' => Http::response(['features' => [['geometry' => ['coordinates' => [9.99, 48.4]], 'properties' => ['label' => 'Ulm, BW, Deutschland', 'locality' => 'Ulm']]]])]);
            foreach ([1, 2, 3] as $durchlauf) {
                $hits = $this->as(Role::Disponent)->getJson('/api/geocode?q=Ulm')->assertOk()->json('data');
                $this->assertSame('Ulm, BW, Deutschland', $hits[0]['label'], "Suche, Durchlauf $durchlauf");
            }
            Http::assertSentCount(1);

            Http::swap(new Factory);
            $this->fakeRoute([[200, 2.5]]);
            foreach ([1, 2, 3] as $durchlauf) {
                $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertOk()->json();
                $this->assertSame(200000, $r['distance_m'], "Route, Durchlauf $durchlauf");
            }
            Http::assertSentCount(1);
        } finally {
            Cache::flush();
            Cache::forgetDriver();
            foreach (glob($dir.'/*') ?: [] as $f) {
                is_dir($f) ? array_map('unlink', glob($f.'/*') ?: []) : unlink($f);
            }
        }
    }

    public function test_alter_kaputter_zwischenspeichereintrag_wird_uebergangen(): void
    {
        $this->fakeRoute([[200, 2.5]]);
        // Wie von der früheren Programmversion abgelegt: ein Objekt statt eines Arrays
        Cache::flush();
        $key = 'ors:v2:geocode:'.sha1('ulm|5');
        Cache::put($key, new \stdClass, 60);
        Http::fake(['*/geocode/search*' => Http::response(['features' => []])]);

        $this->as(Role::Disponent)->getJson('/api/geocode?q=Ulm')->assertOk()->assertJsonPath('data', []);
    }

    public function test_gleiche_tour_wird_zwischengespeichert(): void
    {
        $this->fakeRoute([[200, 2.5]]);

        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertOk();
        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload(['start_at' => '2027-01-01T08:00:00+01:00']))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_linienzug_fuer_die_karte(): void
    {
        $this->fakeRoute([[200, 2.5]], [[10.57, 49.30], [11.0, 48.8], [11.58, 48.14]]);

        $r = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertOk()->json();

        // Dienst liefert [lon, lat]; die Schnittstelle gibt [lat, lon] zurück.
        $this->assertEquals([[49.3, 10.57], [48.8, 11.0], [48.14, 11.58]], $r['geometry']);
    }

    public function test_anfrage_an_den_dienst_hat_die_richtigen_kopfzeilen_und_liefert_abschnitte(): void
    {
        $this->fakeRoute([[200, 2.5]]);

        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertOk();

        Http::assertSent(function (Request $req) {
            // Aus der Praxis (echter Dienst): Ohne application/geo+json antwortet er mit 406 (Code 2007). Ohne Anweisungen
            // (instructions=false) fehlen die Abschnitte, ebenso mit geometry_simplify.
            return str_contains($req->header('Accept')[0] ?? '', 'application/geo+json')
                && ! array_key_exists('instructions', $req->data())
                && ! array_key_exists('geometry_simplify', $req->data())
                && $req->data()['coordinates'][0] === [11.58, 48.14]; // [lon, lat]
        });
    }

    public function test_langer_linienzug_wird_ausgeduennt_anfang_und_ende_bleiben(): void
    {
        $coordinates = array_map(fn ($i) => [10 + $i / 1000, 49 - $i / 1000], range(0, 4999));
        $this->fakeRoute([[400, 5.0]], $coordinates);

        $line = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertOk()->json('geometry');

        $this->assertLessThanOrEqual(1501, count($line));
        $this->assertGreaterThan(1000, count($line));
        $this->assertEquals([49.0, 10.0], $line[0]);
        $this->assertEquals([49 - 4.999, 10 + 4.999], end($line));
    }

    // ---------- Eingaben ----------

    public function test_mindestens_zwei_orte_noetig(): void
    {
        $this->as(Role::Disponent)->postJson('/api/routes/calculate', ['stops' => [self::A]])->assertUnprocessable()->assertJsonValidationErrors('stops');
        // Mit Betriebshof genügt ein Halt (Depot → Halt → Depot).
        $this->fakeRoute([[100, 1.0], [100, 1.0]]);
        $this->as(Role::Disponent)->postJson('/api/routes/calculate', ['stops' => [self::A], 'depot' => self::DEPOT])->assertOk();
    }

    public function test_ungueltige_koordinaten_und_zu_viele_halte(): void
    {
        Http::fake();

        $this->as(Role::Disponent)->postJson('/api/routes/calculate', ['stops' => [['lat' => 95, 'lon' => 10], self::B]])
            ->assertUnprocessable()->assertJsonValidationErrors('stops.0.lat');
        $this->as(Role::Disponent)->postJson('/api/routes/calculate', ['stops' => array_fill(0, 21, self::B)])
            ->assertUnprocessable()->assertJsonValidationErrors('stops');
        $this->as(Role::Disponent)->postJson('/api/routes/calculate', ['stops' => [self::A, self::B], 'extended_days' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors('extended_days');
        Http::assertNothingSent();
    }

    public function test_archiviertes_oder_unbekanntes_fahrzeug_wird_abgelehnt(): void
    {
        Http::fake();
        $archiviert = Vehicle::factory()->create();
        $archiviert->delete();

        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload(['vehicle_id' => $archiviert->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload(['vehicle_id' => '9b0c6a52-6f0e-4d2f-8a70-0a0a0a0a0a0a']))
            ->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
    }

    public function test_fehlermeldungen_sind_deutsch(): void
    {
        $msg = $this->as(Role::Disponent)->postJson('/api/routes/calculate', ['stops' => [['lat' => 95, 'lon' => 10], self::B]])->json('errors')['stops.0.lat'][0];

        $this->assertStringContainsString('Breitengrad des Halts', $msg);
    }

    // ---------- Fehler des Dienstes ----------

    public function test_ohne_zugangsschluessel_kommt_eine_klare_meldung_kein_serverfehler(): void
    {
        config(['services.openrouteservice.key' => null]);
        Http::fake();

        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())
            ->assertStatus(424)->assertJsonPath('reason', 'not_configured')->assertJsonPath('message', fn ($m) => str_contains($m, 'noch nicht eingerichtet'));
        $this->as(Role::Disponent)->getJson('/api/geocode?q=Ulm')->assertStatus(424);
        Http::assertNothingSent();
    }

    public function test_keine_strasse_in_der_naehe_ist_ein_eingabefehler(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 2010, 'message' => 'Could not find routable point within a radius of 350.0 meters']], 404)]);

        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())
            ->assertStatus(422)->assertJsonPath('reason', 'no_route')->assertJsonPath('message', fn ($m) => str_contains($m, 'keine befahrbare Straße'));
    }

    public function test_keine_route_zwischen_den_orten_erklaert_die_fahrzeugmasse(): void
    {
        // Echte Antwort des Dienstes (Code 2009): München Hbf mit 3,65 m Fahrzeughöhe
        Http::fake(['*' => Http::response(['error' => ['code' => 2009, 'message' => 'Route could not be found - Unable to find a route between points 1 and 2.']], 404)]);

        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())
            ->assertStatus(422)->assertJsonPath('reason', 'no_route')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Zwischen den Orten') && str_contains($m, 'Unterführung'));
    }

    public function test_dienstfehler_werden_nie_als_5xx_weitergegeben(): void
    {
        // 502/503/504 würde das Frontend als "Server nicht erreichbar" deuten; hier ist aber nur der Fremddienst gestört.
        foreach ([[429, 'limit'], [500, 'unavailable'], [502, 'unavailable'], [503, 'unavailable'], [401, 'not_configured'], [403, 'not_configured']] as [$status, $reason]) {
            Cache::flush();
            Http::swap(new Factory); // frische Attrappe: Sonst gilt die erste Attrappe weiter
            Http::fake(['*' => Http::response('boom', $status)]);

            $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())
                ->assertStatus(424)->assertJsonPath('reason', $reason);
        }
    }

    public function test_clientfehler_werden_nicht_wiederholt_serverfehler_einmal(): void
    {
        // Wiederholen kostet Kontingent und ändert bei falschem Schlüssel, fehlendem Weg oder erreichtem Limit nichts.
        Http::fake(['*' => Http::response('x', 429)]);
        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertStatus(424);
        Http::assertSentCount(1);

        Cache::flush();
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('x', 500)]);
        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertStatus(424);
        Http::assertSentCount(2);
    }

    public function test_dienst_nicht_erreichbar(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())
            ->assertStatus(424)->assertJsonPath('reason', 'unavailable')->assertJsonPath('message', fn ($m) => str_contains($m, 'antwortet nicht'));
    }

    public function test_abgelehnte_route_ist_ein_eingabefehler(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 2004, 'message' => 'Request parameters exceed the server configuration limits']], 400)]);

        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertStatus(422)->assertJsonPath('reason', 'rejected');
    }

    public function test_antwort_ohne_route_oder_mit_falscher_abschnittszahl(): void
    {
        Http::fake(['*' => Http::response(['features' => []])]);
        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertStatus(424);

        Cache::flush();
        // Drei Orte, aber nur ein Abschnitt geliefert
        $this->fakeRoute([[200, 2.5]]);
        $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload(['depot' => self::DEPOT]))->assertStatus(424);
    }

    public function test_fehlermeldung_enthaelt_nie_den_schluessel(): void
    {
        Http::fake(['*' => Http::response('Invalid key '.self::KEY, 403)]);

        $body = $this->as(Role::Disponent)->postJson('/api/routes/calculate', $this->payload())->assertStatus(424)->getContent();

        $this->assertStringNotContainsString(self::KEY, $body);
    }
}
