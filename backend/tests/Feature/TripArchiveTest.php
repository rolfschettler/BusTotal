<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SectionKind;
use App\Models\Journey;
use App\Models\JourneySection;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tour-Archiv: Touren mit Halten, Fahrzeugbedarf, Strecke und Linienzug speichern. Die Orte landen in der zentralen Tabelle,
 * der Adressdienst wird dafür nie angerufen.
 */
class TripArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Http::fake();
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function body(array $over = []): array
    {
        return array_merge([
            'title' => 'München – Wien',
            'trip_kind' => 'gelegenheit',
            'vehicle_type' => 'doppeldecker',
            'vehicle_count' => 2,
            'round_trip' => false,
            'return_to_depot' => false,
            'loaded_km' => 435.2,
            'empty_km' => 12.5,
            'drive_s' => 18000,
            'elapsed_s' => 40000,
            'days' => 1,
            'route' => ['legs' => [['from' => ['label' => 'München'], 'to' => ['label' => 'Wien']]], 'schedule' => []],
            'geometry' => [[48.13743, 11.57549], [48.2, 12.0], [48.2082, 16.3738]],
            'stops' => [
                ['label' => 'München Hauptbahnhof, München', 'lat' => 48.14, 'lon' => 11.56, 'dwell_minutes' => 0],
                ['label' => 'Wien Westbahnhof, Wien', 'lat' => 48.2, 'lon' => 16.34, 'dwell_minutes' => 30, 'driver_free' => true],
            ],
        ], $over);
    }

    // ---------- Rechte ----------

    public function test_rechte(): void
    {
        $this->getJson('/api/trips')->assertUnauthorized();

        $this->as(Role::Fahrer)->getJson('/api/trips')->assertForbidden();
        foreach ([Role::Admin, Role::Disponent, Role::Buchhaltung] as $role) {
            $this->as($role)->getJson('/api/trips')->assertOk();
        }

        $this->as(Role::Buchhaltung)->postJson('/api/trips', $this->body())->assertForbidden();
        $this->as(Role::Fahrer)->postJson('/api/trips', $this->body())->assertForbidden();
        $this->as(Role::Disponent)->postJson('/api/trips', $this->body())->assertCreated();
        $this->as(Role::Admin)->postJson('/api/trips', $this->body())->assertCreated();

        $id = Trip::first()->id;
        $this->as(Role::Buchhaltung)->putJson("/api/trips/$id", $this->body())->assertForbidden();
        $this->as(Role::Buchhaltung)->deleteJson("/api/trips/$id")->assertForbidden();
    }

    // ---------- Speichern ----------

    public function test_tour_wird_mit_halten_orten_und_linie_gespeichert(): void
    {
        $res = $this->as(Role::Disponent)->postJson('/api/trips', $this->body())->assertCreated()
            ->assertJsonPath('title', 'München – Wien')
            ->assertJsonPath('vehicle_type', 'doppeldecker')
            ->assertJsonPath('vehicle_count', 2)
            ->assertJsonPath('stops.0.position', 1)
            ->assertJsonPath('stops.0.place.lat', 48.14)
            ->assertJsonPath('stops.1.dwell_minutes', 30)
            ->assertJsonPath('stops.1.driver_free', true)
            ->assertJsonCount(3, 'geometry');

        $this->assertDatabaseCount('places', 2);
        $this->assertDatabaseCount('trip_stops', 2);
        $this->assertNotNull(Trip::first()->created_by);
        Http::assertNothingSent();   // der Adressdienst wird für gespeicherte Touren nie gefragt
        $this->assertNotNull($res->json('route.legs'));
    }

    public function test_gleicher_halt_in_zwei_touren_teilt_sich_den_ort(): void
    {
        $this->as(Role::Disponent)->postJson('/api/trips', $this->body())->assertCreated();
        $this->as(Role::Disponent)->postJson('/api/trips', $this->body(['title' => 'Zweite']))->assertCreated();

        $this->assertDatabaseCount('trips', 2);
        $this->assertDatabaseCount('places', 2);
    }

    public function test_gleiches_label_mit_anderen_koordinaten_aendert_keinen_vorhandenen_ort(): void
    {
        $this->as(Role::Disponent)->postJson('/api/trips', $this->body())->assertCreated();
        $original = Place::where('label', 'München Hauptbahnhof, München')->firstOrFail();

        $other = $this->body();
        $other['stops'][0]['lat'] = 10.0;
        $this->as(Role::Disponent)->postJson('/api/trips', $other)->assertCreated();

        $this->assertEquals(48.14, $original->fresh()->lat);
        $this->assertDatabaseCount('places', 3);
    }

    public function test_linie_wird_gekuerzt_und_bereinigt(): void
    {
        $res = $this->as(Role::Disponent)->postJson('/api/trips', $this->body(['geometry' => [[48.123456789, 11.987654321], ['x', 'y'], [1]]]))->assertCreated();

        $this->assertSame([[48.12346, 11.98765]], $res->json('geometry'));
    }

    public function test_pflichtangaben_und_grenzen(): void
    {
        $this->as(Role::Disponent)->postJson('/api/trips', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'trip_kind', 'vehicle_count', 'loaded_km', 'stops']);

        $this->as(Role::Disponent)->postJson('/api/trips', $this->body(['stops' => [['label' => 'Nur einer', 'lat' => 1, 'lon' => 1]]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['stops']);
        $this->as(Role::Disponent)->postJson('/api/trips', $this->body(['trip_kind' => 'unsinn']))->assertUnprocessable()->assertJsonValidationErrors(['trip_kind']);
        $this->as(Role::Disponent)->postJson('/api/trips', $this->body(['vehicle_type' => 'ufo']))->assertUnprocessable()->assertJsonValidationErrors(['vehicle_type']);
        $this->as(Role::Disponent)->postJson('/api/trips', $this->body(['vehicle_count' => 0]))->assertUnprocessable()->assertJsonValidationErrors(['vehicle_count']);

        $bad = $this->body();
        $bad['stops'][0]['lat'] = 123;
        $this->as(Role::Disponent)->postJson('/api/trips', $bad)->assertUnprocessable()->assertJsonValidationErrors(['stops.0.lat']);

        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('places', 0);
    }

    // ---------- Liste und Suche ----------

    public function test_liste_ohne_linie_und_zeitplan_einzelabruf_mit(): void
    {
        $id = $this->as(Role::Disponent)->postJson('/api/trips', $this->body())->json('id');

        $list = $this->as(Role::Buchhaltung)->getJson('/api/trips')->assertOk()->assertJsonCount(1, 'data');
        $this->assertArrayNotHasKey('geometry', $list->json('data.0'));
        $this->assertArrayNotHasKey('route', $list->json('data.0'));
        $list->assertJsonPath('data.0.stops.1.label', 'Wien Westbahnhof, Wien');

        $this->as(Role::Buchhaltung)->getJson("/api/trips/$id")->assertOk()->assertJsonCount(3, 'geometry')->assertJsonStructure(['route']);
    }

    public function test_suche_am_wortanfang_in_titel_und_orten(): void
    {
        $this->as(Role::Disponent)->postJson('/api/trips', $this->body())->assertCreated();
        $this->as(Role::Disponent)->postJson('/api/trips', $this->body(['title' => 'Ulm – Salzburg', 'stops' => [
            ['label' => 'Ulm Hauptbahnhof', 'lat' => 48.4, 'lon' => 9.98],
            ['label' => 'Salzburg Hauptbahnhof', 'lat' => 47.8, 'lon' => 13.04],
        ]]))->assertCreated();

        $this->as(Role::Disponent)->getJson('/api/trips?q=wien')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'München – Wien');
        $this->as(Role::Disponent)->getJson('/api/trips?q=salz')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Ulm – Salzburg');
        $this->as(Role::Disponent)->getJson('/api/trips?q=westbahn')->assertJsonCount(1, 'data');   // Wortanfang im Ortsnamen
        $this->as(Role::Disponent)->getJson('/api/trips?q=ünchen')->assertJsonCount(0, 'data');      // nie mitten im Wort
        $this->as(Role::Disponent)->getJson('/api/trips?q=100%25')->assertJsonCount(0, 'data');      // Platzhalter wirken nicht
    }

    // ---------- Ändern und Löschen ----------

    public function test_aendern_ersetzt_die_halte(): void
    {
        $id = $this->as(Role::Disponent)->postJson('/api/trips', $this->body())->json('id');

        $new = $this->body(['title' => 'Neu', 'stops' => [
            ['label' => 'A', 'lat' => 1, 'lon' => 1],
            ['label' => 'B', 'lat' => 2, 'lon' => 2],
            ['label' => 'C', 'lat' => 3, 'lon' => 3],
        ]]);
        $this->as(Role::Disponent)->putJson("/api/trips/$id", $new)->assertOk()->assertJsonPath('title', 'Neu')->assertJsonCount(3, 'stops');

        $this->assertDatabaseCount('trip_stops', 3);
        $this->assertDatabaseCount('trips', 1);
    }

    public function test_loeschen_und_sperre_durch_reise(): void
    {
        $id = $this->as(Role::Disponent)->postJson('/api/trips', $this->body())->json('id');

        $journey = Journey::create(['title' => 'Wienreise']);
        JourneySection::create(['journey_id' => $journey->id, 'position' => 1, 'kind' => SectionKind::Hinfahrt, 'trip_id' => $id]);

        $this->as(Role::Disponent)->deleteJson("/api/trips/$id")->assertUnprocessable()
            ->assertJsonPath('message', 'Diese Tour wird in einer Reise verwendet und kann nicht gelöscht werden.');
        $this->assertDatabaseCount('trips', 1);

        JourneySection::query()->delete();
        $this->as(Role::Disponent)->deleteJson("/api/trips/$id")->assertNoContent();
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('trip_stops', 0);
        $this->assertDatabaseCount('places', 2);   // Orte bleiben für andere Datensätze
    }

    public function test_aenderungsprotokoll_enthaelt_nicht_die_linie(): void
    {
        $this->as(Role::Disponent)->postJson('/api/trips', $this->body())->assertCreated();

        $log = \App\Models\AuditLog::query()->latest()->first();
        $this->assertStringNotContainsString('geometry', json_encode($log->new_values ?? $log->getAttributes()));
    }
}
