<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Journey;
use App\Models\JourneySection;
use App\Models\PricingSetting;
use App\Models\PricingVehicleRate;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Angebot mit mehreren Strecken und Fahrzeugtypen: Aus dem Tour-Archiv kommen weitere Abschnitte der Reise in den Entwurf,
 * je Fahrzeugtyp mit den Preisen des Typs; jede Position kennt ihren Abschnitt und Fahrzeugtyp.
 */
class JourneySectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake();
        $this->seed(SettingsSeeder::class);
        PricingSetting::current()->update(['price_per_km' => 2, 'empty_km_percent' => 100, 'tax_rate' => 19, 'hourly_rate' => 0, 'day_rate' => 0, 'overnight_rate' => 0, 'minimum_price' => 0]);
        PricingVehicleRate::create(['vehicle_type' => 'minibus', 'price_per_km' => 1]);
        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457',
            'city' => 'Hamburg', 'tax_number' => '12/345/67890', 'payment_term_days' => 14,
        ]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function trip(string $title = 'Wien – Wien Rundfahrt', float $km = 100): string
    {
        return $this->as(Role::Disponent)->postJson('/api/trips', [
            'title' => $title, 'trip_kind' => 'gelegenheit', 'vehicle_count' => 1, 'loaded_km' => $km, 'empty_km' => 0,
            'drive_s' => 3600, 'elapsed_s' => 7200, 'days' => 1,
            'stops' => [['label' => 'Wien Hbf', 'lat' => 48.18, 'lon' => 16.37], ['label' => 'Schönbrunn', 'lat' => 48.18, 'lon' => 16.31]],
        ])->assertCreated()->json('id');
    }

    /** Angebot aus der Tour München – Wien: 400 km mit einem Doppeldecker zu 2 € (800 €). */
    private function quote(): array
    {
        return $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', [
            'loaded_km' => 400, 'empty_km' => 0, 'drive_s' => 18000, 'days' => 1, 'label' => 'München – Wien',
            'vehicle_type' => 'doppeldecker', 'vehicle_count' => 1,
        ])->assertCreated()->json();
    }

    /**
     * Strecke auf der Reise hinzufügen und das Angebot abgleichen (wie die Oberfläche: „Strecke hinzufügen“, dann „Angebot
     * aktualisieren“). Gibt den Beleg zurück; bei Prüffehlern die Antwort des Endpunkts.
     */
    private function addTo(string $docId, array $body, Role $role = Role::Disponent)
    {
        $journeyId = Document::findOrFail($docId)->journey_id;
        $res = $this->as($role)->postJson("/api/journeys/$journeyId/sections", $body);
        if ($res->status() !== 201) {
            return $res;
        }
        $quoted = $this->as($role)->postJson("/api/journeys/$journeyId/quote");
        if ($quoted->status() !== 200) {
            return $quoted;
        }

        return $this->as($role)->getJson("/api/documents/$docId");
    }

    private function add(string $docId, array $over = [], Role $role = Role::Disponent)
    {
        // Die Tour zuerst anlegen: Sie wird als Disponent gespeichert und darf den angemeldeten Benutzer des Aufrufs nicht überschreiben.
        $body = array_merge(['kind' => 'vor_ort', 'trip_id' => $over['trip_id'] ?? $this->trip(), 'vehicles' => [['type' => 'doppeldecker', 'count' => 1]]], $over);

        return $this->addTo($docId, $body, $role);
    }

    public function test_die_erste_strecke_bekommt_abschnitt_fahrzeugbedarf_und_positionen_mit_zuordnung(): void
    {
        $quote = $this->quote();

        $section = Journey::firstOrFail()->sections()->with('vehicles')->firstOrFail();
        $this->assertSame('doppeldecker', $section->vehicles->first()->vehicle_type->value);
        $this->assertSame(1, $section->vehicles->first()->vehicle_count);

        $doc = $this->as(Role::Disponent)->getJson("/api/documents/{$quote['id']}")->assertOk();
        $doc->assertJsonPath('lines.0.journey_section_id', $section->id)
            ->assertJsonPath('lines.0.vehicle_type', 'doppeldecker')
            ->assertJsonPath('lines.0.description', 'Hinfahrt: München – Wien · Doppeldeckerbus')
            ->assertJsonPath('journey.sections.0.id', $section->id)
            ->assertJsonPath('journey.sections.0.kind_label', 'Hinfahrt')
            ->assertJsonPath('journey.sections.0.vehicles.0.label', 'Doppeldeckerbus');
    }

    public function test_weitere_strecke_mit_zwei_fahrzeugtypen_ergibt_positionen_je_typ_mit_deren_preisen(): void
    {
        $quote = $this->quote();

        $res = $this->add($quote['id'], ['vehicles' => [['type' => 'doppeldecker', 'count' => 1], ['type' => 'minibus', 'count' => 2]]])
            ->assertOk();

        // erste Strecke: 400 km × 2 € = 800 €; neue Strecke: 100 km × 2 € = 200 € (Doppeldecker) und 2 × 100 km × 1 € = 200 € (Kleinbus)
        $res->assertJsonCount(3, 'lines')->assertJsonPath('net_total', '1200.00');
        $this->assertSame(['doppeldecker', 'doppeldecker', 'minibus'], array_column($res->json('lines'), 'vehicle_type'));
        $this->assertSame('Fahrt vor Ort: Wien – Wien Rundfahrt · 2 × Minibus / Kleinbus', $res->json('lines.2.description'));

        $journey = Journey::firstOrFail();
        $this->assertSame(2, $journey->sections()->count());
        $second = $journey->sections()->where('position', 2)->with('vehicles')->firstOrFail();
        $this->assertSame('vor_ort', $second->kind->value);
        $this->assertSame(2, $second->vehicles->count());
        $this->assertSame($second->id, $res->json('lines.1.journey_section_id'));
        $res->assertJsonPath('journey.sections.1.vehicles.1.count', 2);
    }

    // ---------- Fahrt vor Ort ohne Tour: eigene Angaben ----------

    private function addOwn(string $docId, array $own = [], array $over = [])
    {
        return $this->addTo($docId, array_merge([
            'kind' => 'vor_ort',
            'own' => array_merge(['title' => 'Ausflug Ossiacher See', 'loaded_km' => 60, 'drive_minutes' => 150, 'days' => 1], $own),
            'vehicles' => [['type' => 'doppeldecker', 'count' => 1]],
        ], $over));
    }

    // Die Tests unten legen die Strecke auf der Reise an und gleichen dann das Angebot ab.
    public function test_fahrt_vor_ort_mit_eigenen_angaben_ohne_tour(): void
    {
        $quote = $this->quote();

        $res = $this->addOwn($quote['id'])->assertOk();

        // 400 km + 60 km, je 2 € (Leerfahrt gibt es vor Ort nicht)
        $res->assertJsonCount(2, 'lines')->assertJsonPath('net_total', '920.00')
            ->assertJsonPath('journey.sections.1.title', 'Ausflug Ossiacher See')
            ->assertJsonPath('journey.sections.1.trip_id', null)
            ->assertJsonPath('journey.sections.1.own.loaded_km', 60)
            ->assertJsonPath('journey.sections.1.own.drive_s', 9000);
        $this->assertStringStartsWith('Fahrt vor Ort: Ausflug Ossiacher See · Doppeldeckerbus', $res->json('lines.1.description'));
        $this->assertDatabaseCount('trips', 0);   // im Archiv entsteht nichts
    }

    public function test_fahrt_vor_ort_steht_festgeschrieben_in_der_fahrtbeschreibung(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
        $quote = $this->quote();
        $lines = $this->addOwn($quote['id'])->assertOk()->json('lines');
        $this->as(Role::Disponent)->putJson("/api/documents/{$quote['id']}", ['customer_id' => $customer->id, 'lines' => $lines])->assertOk();

        $doc = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();

        $own = collect($doc->json('tour_snapshot.sections'))->firstWhere('title', 'Ausflug Ossiacher See');
        $this->assertNotNull($own);
        $this->assertNull($own['trip']);
        $this->assertSame(['loaded_km' => 60, 'drive_s' => 9000, 'days' => 1], $own['own']);
        $this->as(Role::Disponent)->getJson("/api/journeys/{$quote['journey_id']}")->assertOk()
            ->assertJsonPath('sections.1.own.days', 1)->assertJsonPath('sections.1.trip', null);
    }

    public function test_eigene_angaben_werden_geprueft(): void
    {
        $quote = $this->quote();

        $this->addOwn($quote['id'], ['title' => ''])->assertUnprocessable()->assertJsonValidationErrors(['own.title']);
        $this->addOwn($quote['id'], ['loaded_km' => 0])->assertUnprocessable()->assertJsonValidationErrors(['own.loaded_km']);
        $this->addOwn($quote['id'], ['drive_minutes' => 0])->assertUnprocessable()->assertJsonValidationErrors(['own.drive_minutes']);
        $this->addOwn($quote['id'], ['days' => 0])->assertUnprocessable()->assertJsonValidationErrors(['own.days']);
        // weder Tour noch eigene Angaben, oder beides
        $this->addTo($quote['id'], ['kind' => 'vor_ort', 'vehicles' => [['type' => 'doppeldecker', 'count' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['trip_id']);
        $this->addOwn($quote['id'], [], ['trip_id' => $this->trip()])->assertUnprocessable()->assertJsonValidationErrors(['trip_id']);

        $this->assertSame(1, JourneySection::count());
    }

    public function test_die_zuordnung_ueberlebt_speichern_und_folgebelege(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
        $quote = $this->quote();
        $added = $this->add($quote['id'])->assertOk()->json();

        // Das Formular schickt die Positionen mit Abschnitt und Typ zurück.
        $lines = array_map(fn (array $l) => array_intersect_key($l, array_flip(['title', 'description', 'quantity', 'unit', 'trip_kind', 'unit_price', 'tax_rate', 'tax_note', 'journey_section_id', 'vehicle_type'])), $added['lines']);
        $saved = $this->as(Role::Disponent)->putJson("/api/documents/{$quote['id']}", ['customer_id' => $customer->id, 'lines' => $lines])->assertOk()->json();
        $this->assertSame(array_column($added['lines'], 'journey_section_id'), array_column($saved['lines'], 'journey_section_id'));

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json();
        $this->assertSame(array_column($added['lines'], 'journey_section_id'), array_column($order['lines'], 'journey_section_id'));
        $this->assertSame(array_column($added['lines'], 'vehicle_type'), array_column($order['lines'], 'vehicle_type'));
    }

    public function test_abschnitt_aus_fremder_reise_wird_abgelehnt(): void
    {
        $quote = $this->quote();
        $other = $this->quote();
        $foreign = Journey::findOrFail($other['journey_id'])->sections()->firstOrFail();

        $this->as(Role::Disponent)->putJson("/api/documents/{$quote['id']}", ['lines' => [
            ['title' => 'X', 'quantity' => 1, 'unit' => 'Stk', 'unit_price' => '10', 'tax_rate' => 19, 'journey_section_id' => $foreign->id],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['document']);
    }

    public function test_fehlende_preise_lassen_das_angebot_unveraendert(): void
    {
        $quote = $this->quote();
        $trip = $this->trip();
        PricingSetting::current()->update(['price_per_km' => 0]);
        PricingVehicleRate::query()->delete();

        // Die Strecke ist auf der Reise angelegt (Planung), die Preisfindung für das Angebot scheitert mit klarer Meldung
        $this->add($quote['id'], ['trip_id' => $trip])->assertUnprocessable()->assertJsonValidationErrors(['pricing']);

        $this->assertSame(2, JourneySection::count());
        $this->assertCount(1, $this->as(Role::Disponent)->getJson("/api/documents/{$quote['id']}")->json('lines'));
        $this->as(Role::Disponent)->getJson("/api/journeys/{$quote['journey_id']}/quote-status")->assertOk()->assertJsonPath('sections.1.state', 'neu');
    }

    public function test_eingaben_werden_geprueft(): void
    {
        $quote = $this->quote();
        $trip = $this->trip();

        $this->add($quote['id'], ['trip_id' => $trip, 'vehicles' => []])->assertUnprocessable()->assertJsonValidationErrors(['vehicles']);
        $this->add($quote['id'], ['trip_id' => $trip, 'vehicles' => [['type' => 'ufo', 'count' => 1]]])->assertUnprocessable()->assertJsonValidationErrors(['vehicles.0.type']);
        $this->add($quote['id'], ['trip_id' => $trip, 'vehicles' => [['type' => 'minibus', 'count' => 0]]])->assertUnprocessable()->assertJsonValidationErrors(['vehicles.0.count']);
        $this->add($quote['id'], ['trip_id' => $trip, 'vehicles' => [['type' => 'minibus', 'count' => 1], ['type' => 'minibus', 'count' => 2]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['vehicles.0.type']);
        $this->add($quote['id'], ['trip_id' => '3f0e0f6e-7c8b-4c1a-9a53-0d2b1f5b7a11'])->assertUnprocessable()->assertJsonValidationErrors(['trip_id']);
        $this->add($quote['id'], ['trip_id' => $trip, 'kind' => 'quatsch'])->assertUnprocessable()->assertJsonValidationErrors(['kind']);

        $this->assertSame(1, JourneySection::count());
    }

    public function test_nach_dem_festschreiben_wird_das_angebot_nicht_mehr_abgeglichen(): void
    {
        $quote = $this->quote();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertUnprocessable();   // ohne Kunde nicht festschreibbar
        $customer = Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
        $lines = $this->as(Role::Disponent)->getJson("/api/documents/{$quote['id']}")->json('lines');
        $this->as(Role::Disponent)->putJson("/api/documents/{$quote['id']}", ['customer_id' => $customer->id, 'service_date_from' => '2027-05-10', 'lines' => $lines])->assertOk();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();

        // Die Strecke lässt sich auf der Reise planen, aber das festgeschriebene Angebot bleibt, wie es ist
        $journeyId = $quote['journey_id'];
        $this->as(Role::Disponent)->postJson("/api/journeys/$journeyId/sections", ['kind' => 'rueckfahrt', 'trip_id' => $this->trip(), 'vehicles' => [['type' => 'doppeldecker', 'count' => 1]]])->assertCreated();
        $this->as(Role::Disponent)->postJson("/api/journeys/$journeyId/quote")->assertUnprocessable()->assertJsonValidationErrors(['journey']);
        $this->assertCount(1, $this->as(Role::Disponent)->getJson("/api/documents/{$quote['id']}")->json('lines'));
    }

    public function test_strecke_entfernen(): void
    {
        $quote = $this->quote();
        $added = $this->add($quote['id'])->assertOk()->json();
        $sectionId = $added['lines'][1]['journey_section_id'];

        $this->as(Role::Disponent)->deleteJson("/api/journeys/{$quote['journey_id']}/sections/$sectionId")->assertOk();

        // Die Positionen der Strecke gehen aus dem Entwurf mit
        $doc = $this->as(Role::Disponent)->getJson("/api/documents/{$quote['id']}")->assertOk();
        $doc->assertJsonCount(1, 'lines')->assertJsonPath('net_total', '800.00');
        $this->assertSame(1, JourneySection::count());
        $this->assertDatabaseCount('journey_section_vehicles', 1);
    }

    public function test_fremde_strecken_gehen_nicht_und_eine_festgeschriebene_strecke_bleibt(): void
    {
        $quote = $this->quote();
        $other = $this->quote();
        $foreign = Journey::findOrFail($other['journey_id'])->sections()->firstOrFail();
        $this->as(Role::Disponent)->deleteJson("/api/journeys/{$quote['journey_id']}/sections/{$foreign->id}")->assertNotFound();

        $customer = Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
        $lines = $this->as(Role::Disponent)->getJson("/api/documents/{$quote['id']}")->json('lines');
        $this->as(Role::Disponent)->putJson("/api/documents/{$quote['id']}", ['customer_id' => $customer->id, 'lines' => $lines])->assertOk();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();
        $first = Journey::findOrFail($quote['journey_id'])->sections()->firstOrFail();
        $this->as(Role::Disponent)->deleteJson("/api/journeys/{$quote['journey_id']}/sections/{$first->id}")->assertUnprocessable()->assertJsonValidationErrors(['journey']);
        $this->assertSame(2, JourneySection::count());
    }

    public function test_entwurf_loeschen_loescht_abschnitte_und_fahrzeugbedarf_mit(): void
    {
        $quote = $this->quote();
        $this->add($quote['id'])->assertOk();

        $this->as(Role::Disponent)->deleteJson("/api/documents/{$quote['id']}")->assertOk();

        $this->assertSame(0, Document::count());
        $this->assertSame(0, Journey::count());
        $this->assertDatabaseCount('journey_sections', 0);
        $this->assertDatabaseCount('journey_section_vehicles', 0);
    }

    // ---------- Strecke in der Streckenplanung ändern ----------

    /** @return array<string, mixed> eine berechnete Tour mit Plan, wie sie die Streckenplanung schickt */
    private function planned(float $km = 80): array
    {
        return [
            'title' => 'Villach – Kobarid', 'loaded_km' => $km, 'empty_km' => 0, 'drive_s' => 5400, 'elapsed_s' => 5400, 'days' => 1,
            'stops' => [['label' => 'Villach'], ['label' => 'Kobarid']],
            'plan' => [
                'depot_id' => null, 'return_to_depot' => false,
                'stops' => [['label' => 'Villach', 'lat' => 46.61, 'lon' => 13.85], ['label' => 'Kobarid', 'lat' => 46.25, 'lon' => 13.58]],
                'route' => ['legs' => []], 'geometry' => [[46.61, 13.85], [46.25, 13.58]],
            ],
        ];
    }

    private function plannedSection(): JourneySection
    {
        $quote = $this->quote();
        $this->addTo($quote['id'], ['kind' => 'vor_ort', 'tour' => $this->planned(), 'vehicles' => [['type' => 'doppeldecker', 'count' => 1]]])->assertOk();

        return JourneySection::where('position', 2)->firstOrFail();
    }

    private function sectionUrl(JourneySection $section): string
    {
        return "/api/journeys/{$section->journey_id}/sections/{$section->id}";
    }

    public function test_geplante_strecke_laesst_sich_mit_orten_und_linie_wieder_oeffnen(): void
    {
        $section = $this->plannedSection();

        $this->as(Role::Disponent)->getJson($this->sectionUrl($section))->assertOk()
            ->assertJsonPath('title', 'Villach – Kobarid')
            ->assertJsonPath('plan.stops.1.label', 'Kobarid')
            ->assertJsonPath('plan.stops.1.lat', 46.25)
            ->assertJsonPath('plan.geometry.0.0', 46.61)
            ->assertJsonPath('plan.calculated.loaded_km', 80)
            ->assertJsonPath('figures.loaded_km', 80);
        // Die Lage der Halte steht nur in der Ortstabelle, und der Plan gelangt nie in die Fahrtbeschreibung.
        $this->assertArrayNotHasKey('lat', $section->plan['stops'][0]);
        $this->assertArrayNotHasKey('plan', $section->tour_data);
    }

    public function test_kennzahlen_von_hand_ueberschreiben_markiert_die_strecke_als_geaendert(): void
    {
        $section = $this->plannedSection();

        $this->as(Role::Disponent)->putJson($this->sectionUrl($section), ['figures' => ['loaded_km' => 150, 'empty_km' => 10, 'drive_s' => 7200, 'days' => 2]])
            ->assertOk()->assertJsonPath('sections.1.trip.loaded_km', 150)->assertJsonPath('sections.1.trip.days', 2)
            ->assertJsonPath('quote.sections.1.state', 'geaendert');

        // Der berechnete Wert bleibt erhalten, damit man ihn wiederherstellen kann.
        $this->as(Role::Disponent)->getJson($this->sectionUrl($section))
            ->assertJsonPath('figures.loaded_km', 150)->assertJsonPath('plan.calculated.loaded_km', 80);
    }

    public function test_neue_route_ersetzt_die_eigenen_angaben(): void
    {
        $quote = $this->quote();
        $this->addOwn($quote['id'])->assertOk();
        $section = JourneySection::where('position', 2)->firstOrFail();

        $this->as(Role::Disponent)->putJson($this->sectionUrl($section), ['tour' => $this->planned(90)])
            ->assertOk()->assertJsonPath('sections.1.own', null)->assertJsonPath('sections.1.trip.loaded_km', 90)
            ->assertJsonPath('sections.1.title', 'Ausflug Ossiacher See');
    }

    public function test_kennzahlen_einer_strecke_ohne_route(): void
    {
        $quote = $this->quote();
        $this->addOwn($quote['id'])->assertOk();
        $section = JourneySection::where('position', 2)->firstOrFail();
        $figures = ['loaded_km' => 75, 'empty_km' => 0, 'drive_s' => 10800, 'days' => 1];

        $this->as(Role::Disponent)->putJson($this->sectionUrl($section), ['figures' => $figures])
            ->assertOk()->assertJsonPath('sections.1.own.loaded_km', 75);
        $this->as(Role::Disponent)->putJson($this->sectionUrl($section), ['figures' => ['loaded_km' => 0] + $figures])
            ->assertUnprocessable()->assertJsonValidationErrors(['figures.loaded_km']);
        $this->as(Role::Buchhaltung)->putJson($this->sectionUrl($section), ['figures' => $figures])->assertForbidden();
    }

    public function test_strecke_aus_dem_archiv_bekommt_den_plan_der_tour(): void
    {
        $quote = $this->quote();
        $this->add($quote['id'])->assertOk();
        $section = JourneySection::where('position', 2)->firstOrFail();

        $this->as(Role::Disponent)->getJson($this->sectionUrl($section))->assertOk()
            ->assertJsonPath('plan.stops.0.label', 'Wien Hbf')->assertJsonPath('plan.stops.0.lat', 48.18);
    }

    public function test_angebot_aus_der_streckenplanung_behaelt_den_plan_und_die_fahrtart_laesst_sich_aendern(): void
    {
        $tour = $this->planned();
        $quote = $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', [
            'loaded_km' => 80, 'empty_km' => 0, 'drive_s' => 5400, 'days' => 1, 'vehicle_type' => 'doppeldecker', 'vehicle_count' => 1, 'tour' => $tour,
        ])->assertCreated()->json();
        $section = JourneySection::firstOrFail();
        $this->assertSame('Kobarid', $section->plan['stops'][1]['label']);

        $this->as(Role::Disponent)->putJson($this->sectionUrl($section), ['trip_kind' => 'quatsch'])->assertUnprocessable();
        $this->as(Role::Disponent)->putJson($this->sectionUrl($section), ['trip_kind' => 'linie'])->assertOk();
        $this->assertSame('linie', $section->fresh()->tour_data['trip_kind']);
        $this->assertNotNull($quote['id']);
    }

    public function test_neue_strecke_mit_von_hand_geaenderten_werten(): void
    {
        $quote = $this->quote();

        $this->addTo($quote['id'], [
            'kind' => 'vor_ort', 'tour' => $this->planned(), 'vehicles' => [['type' => 'doppeldecker', 'count' => 1]],
            'figures' => ['loaded_km' => 120, 'empty_km' => 5, 'drive_s' => 7200, 'days' => 1],
        ])->assertOk();

        $section = JourneySection::where('position', 2)->firstOrFail();
        $this->assertEquals(120, $section->tour_data['loaded_km']);
        $this->assertEquals(80, $section->plan['calculated']['loaded_km']);
    }

    public function test_rechte(): void
    {
        $quote = $this->quote();
        $trip = $this->trip();

        $this->add($quote['id'], ['trip_id' => $trip], Role::Fahrer)->assertForbidden();
        $this->add($quote['id'], ['trip_id' => $trip], Role::Buchhaltung)->assertForbidden();
    }
}
