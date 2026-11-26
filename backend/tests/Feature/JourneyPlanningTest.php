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
 * Die Reise Schritt für Schritt planen: anlegen, Strecken hinzufügen, ändern, entfernen, Angebot daraus erzeugen und abgleichen.
 * Positionen unveränderter Strecken bleiben mit allen Handänderungen unberührt.
 */
class JourneyPlanningTest extends TestCase
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

    private function as(Role $role = Role::Disponent): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function customer(): Customer
    {
        return Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
    }

    private function tour(string $title, float $km = 400): array
    {
        return [
            'title' => $title, 'trip_kind' => 'gelegenheit', 'loaded_km' => $km, 'empty_km' => 0, 'drive_s' => 18000, 'elapsed_s' => 22000, 'days' => 1,
            'round_trip' => false,
            'stops' => [['label' => 'Laupheim', 'dwell_minutes' => 0, 'driver_free' => false], ['label' => 'Villach', 'dwell_minutes' => 0, 'driver_free' => false]],
            'legs' => [['from' => 'Laupheim', 'to' => 'Villach', 'distance_m' => (int) ($km * 1000), 'duration_s' => 18000, 'empty' => false]],
        ];
    }

    private function journey(array $over = []): array
    {
        return $this->as()->postJson('/api/journeys', $over + ['title' => 'Villach-Reise'])->assertCreated()->json();
    }

    private function section(string $journeyId, array $over = [])
    {
        return $this->as()->postJson("/api/journeys/$journeyId/sections", $over + [
            'kind' => 'hinfahrt', 'tour' => $this->tour('Laupheim – Villach'), 'start_on' => '2027-05-10',
            'vehicles' => [['type' => 'doppeldecker', 'count' => 1]],
        ]);
    }

    private function quoteStatus(string $journeyId): array
    {
        return $this->as()->getJson("/api/journeys/$journeyId/quote-status")->assertOk()->json();
    }

    private function states(string $journeyId): array
    {
        return array_column($this->quoteStatus($journeyId)['sections'], 'state');
    }

    public function test_reise_von_hand_anlegen_und_aendern(): void
    {
        $customer = $this->customer();
        $res = $this->journey(['customer_id' => $customer->id]);

        $this->assertSame('Villach-Reise', $res['title']);
        $this->assertSame([], $res['sections']);
        $this->assertSame($customer->id, $res['customer']['id']);
        $this->assertFalse($res['quote']['can_create']);   // noch keine Strecke
        $this->assertTrue(Journey::firstOrFail()->manual);

        $this->as()->putJson("/api/journeys/{$res['id']}", ['title' => 'Villach Pfingsten', 'customer_id' => null])->assertOk()
            ->assertJsonPath('title', 'Villach Pfingsten')->assertJsonPath('customer', null);
        $this->as()->postJson('/api/journeys', ['title' => ''])->assertUnprocessable()->assertJsonValidationErrors(['title']);
    }

    public function test_strecken_aus_tour_archiv_und_eigenen_angaben_und_zeitraum(): void
    {
        $j = $this->journey();
        $trip = $this->as()->postJson('/api/trips', [
            'title' => 'Archivtour', 'trip_kind' => 'gelegenheit', 'vehicle_count' => 1, 'loaded_km' => 100, 'empty_km' => 0,
            'drive_s' => 3600, 'elapsed_s' => 7200, 'days' => 1,
            'stops' => [['label' => 'Wien Hbf', 'lat' => 48.18, 'lon' => 16.37], ['label' => 'Schönbrunn', 'lat' => 48.18, 'lon' => 16.31]],
        ])->assertCreated()->json('id');

        $this->section($j['id'])->assertCreated();
        $this->section($j['id'], ['kind' => 'rueckfahrt', 'tour' => null, 'trip_id' => $trip, 'start_on' => '2027-05-14'])->assertCreated();
        $res = $this->section($j['id'], ['kind' => 'vor_ort', 'tour' => null, 'own' => ['title' => 'Ausflug See', 'loaded_km' => 60, 'drive_minutes' => 150, 'days' => 2], 'start_on' => '2027-05-12'])->assertCreated();

        // Nichts im Archiv außer der einen Tour, jede Strecke hat ihre Kopie bzw. eigene Angaben
        $this->assertDatabaseCount('trips', 1);
        $res->assertJsonCount(3, 'sections')->assertJsonPath('sections.2.own.days', 2)->assertJsonPath('sections.1.start_on', '2027-05-14')
            ->assertJsonPath('date_from', '2027-05-10')->assertJsonPath('date_to', '2027-05-14');
        $this->assertSame(['Laupheim', 'Villach'], array_column(JourneySection::where('position', 1)->firstOrFail()->tour_data['stops'], 'label'));
    }

    public function test_angebot_aus_der_reise_dann_abgleich_nur_geaenderter_strecken(): void
    {
        $customer = $this->customer();
        $j = $this->journey(['customer_id' => $customer->id]);
        $this->section($j['id'])->assertCreated();
        $this->section($j['id'], ['kind' => 'rueckfahrt', 'tour' => $this->tour('Villach – Laupheim'), 'start_on' => '2027-05-14'])->assertCreated();
        $this->assertTrue($this->quoteStatus($j['id'])['can_create']);
        $this->assertSame(['neu', 'neu'], $this->states($j['id']));

        // Angebot erstellen: Entwurf mit beiden Strecken (je 400 km × 2 €), Kunde und Titel aus der Reise
        $created = $this->as()->postJson("/api/journeys/{$j['id']}/quote")->assertCreated()->assertJsonPath('created', true)->json();
        $doc = $this->as()->getJson("/api/documents/{$created['id']}")->assertOk();
        $doc->assertJsonCount(2, 'lines')->assertJsonPath('net_total', '1600.00')->assertJsonPath('customer_id', $customer->id)->assertJsonPath('subject', 'Villach-Reise');
        $this->assertSame(['aktuell', 'aktuell'], $this->states($j['id']));
        $this->assertFalse($this->quoteStatus($j['id'])['can_sync']);

        // Der erste Preis wird von Hand geändert
        $lines = array_map(fn (array $l) => array_intersect_key($l, array_flip(['title', 'description', 'quantity', 'unit', 'trip_kind', 'unit_price', 'tax_rate', 'tax_note', 'journey_section_id', 'vehicle_type'])), $doc->json('lines'));
        $firstSection = $lines[0]['journey_section_id'];
        $lines[0]['unit_price'] = '999.00';
        $this->as()->putJson("/api/documents/{$created['id']}", ['lines' => $lines])->assertOk();

        // Nur die zweite Strecke ändert sich (zwei Kleinbusse zu 1 €/km): sie ist „geändert“, die erste bleibt „aktuell“
        $second = JourneySection::where('position', 2)->firstOrFail();
        $this->as()->putJson("/api/journeys/{$j['id']}/sections/{$second->id}", ['vehicles' => [['type' => 'minibus', 'count' => 2]]])->assertOk();
        $this->assertSame(['aktuell', 'geaendert'], $this->states($j['id']));
        $this->assertTrue($this->quoteStatus($j['id'])['can_sync']);

        $sync = $this->as()->postJson("/api/journeys/{$j['id']}/quote")->assertOk()->assertJsonPath('created', false)->json();
        $this->assertSame($created['id'], $sync['id']);
        $this->assertSame(['Villach – Laupheim'], $sync['updated']);

        $after = $this->as()->getJson("/api/documents/{$created['id']}")->assertOk();
        $byType = collect($after->json('lines'));
        // Erste Strecke: Handänderung bleibt. Zweite Strecke: 2 × 400 km × 1 € = 800 € mit Kleinbus.
        $this->assertSame('999.00', number_format((float) $byType->firstWhere('journey_section_id', $firstSection)['unit_price'], 2, '.', ''));
        $this->assertSame(['minibus'], $byType->where('journey_section_id', $second->id)->pluck('vehicle_type')->unique()->values()->all());
        $this->assertSame(['aktuell', 'aktuell'], $this->states($j['id']));

        // Neue Strecke: „neu“, nach dem Abgleich im Angebot; Entfernen nimmt die Positionen wieder heraus
        $res = $this->section($j['id'], ['kind' => 'vor_ort', 'tour' => $this->tour('Ausflug', 50)])->assertCreated();
        $third = $res->json('sections.2.id');
        $this->assertSame(['aktuell', 'aktuell', 'neu'], $this->states($j['id']));
        $this->as()->postJson("/api/journeys/{$j['id']}/quote")->assertOk();
        $this->assertSame($third, collect($this->as()->getJson("/api/documents/{$created['id']}")->json('lines'))->pluck('journey_section_id')->last());
        $this->as()->deleteJson("/api/journeys/{$j['id']}/sections/$third")->assertOk()->assertJsonCount(2, 'sections');
        $this->assertNotContains($third, collect($this->as()->getJson("/api/documents/{$created['id']}")->json('lines'))->pluck('journey_section_id')->all());
    }

    public function test_ohne_strecke_ohne_preise_und_nach_dem_festschreiben_gibt_es_klare_meldungen(): void
    {
        $customer = $this->customer();
        $j = $this->journey(['customer_id' => $customer->id]);
        $this->as()->postJson("/api/journeys/{$j['id']}/quote")->assertUnprocessable()->assertJsonValidationErrors(['journey']);

        PricingSetting::current()->update(['price_per_km' => 0]);
        PricingVehicleRate::query()->delete();
        $this->section($j['id'])->assertCreated();
        $this->as()->postJson("/api/journeys/{$j['id']}/quote")->assertUnprocessable()->assertJsonValidationErrors(['pricing']);
        $this->assertSame(0, Document::count());   // kein halbes Angebot

        PricingSetting::current()->update(['price_per_km' => 2]);
        $created = $this->as()->postJson("/api/journeys/{$j['id']}/quote")->assertCreated()->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$created['id']}/finalize")->assertOk();
        $this->assertNotNull($this->quoteStatus($j['id'])['issued_id']);
        // Die Strecke steht im festgeschriebenen Angebot, also nicht „neu“; eine spätere schon, eine geänderte heißt „geändert“.
        $this->assertSame(['aktuell'], $this->states($j['id']));
        $this->section($j['id'], ['kind' => 'rueckfahrt'])->assertCreated();
        $this->assertSame(['aktuell', 'neu'], $this->states($j['id']));
        $issuedSection = JourneySection::where('position', 1)->firstOrFail();
        $this->as()->putJson("/api/journeys/{$j['id']}/sections/{$issuedSection->id}", ['surcharge_percent' => 10])->assertOk();
        $this->assertSame(['geaendert', 'neu'], $this->states($j['id']));
        $this->assertFalse($this->quoteStatus($j['id'])['can_sync']);
        $this->as()->postJson("/api/journeys/{$j['id']}/quote")->assertUnprocessable()->assertJsonValidationErrors(['journey']);
        // Die festgeschriebene Strecke bleibt
        $first = JourneySection::where('position', 1)->firstOrFail();
        $this->as()->deleteJson("/api/journeys/{$j['id']}/sections/{$first->id}")->assertUnprocessable()->assertJsonValidationErrors(['journey']);
    }

    public function test_reise_von_hand_bleibt_ohne_entwurf_die_des_kurzwegs_nicht(): void
    {
        $j = $this->journey();
        $this->section($j['id'])->assertCreated();
        $created = $this->as()->postJson("/api/journeys/{$j['id']}/quote")->assertCreated()->json();
        $this->as()->deleteJson("/api/documents/{$created['id']}")->assertOk();
        $this->assertSame(1, Journey::count());   // von Hand geplant: bleibt
        $this->as()->deleteJson("/api/journeys/{$j['id']}")->assertOk();   // ohne Belege löschbar
        $this->assertSame(0, Journey::count());

        // Kurzweg: Die Reise verschwindet mit ihrem einzigen Entwurf
        $quote = $this->as()->postJson('/api/quotes/from-tour', [
            'loaded_km' => 400, 'empty_km' => 0, 'drive_s' => 18000, 'days' => 1, 'label' => 'München – Wien', 'vehicle_type' => 'doppeldecker', 'vehicle_count' => 1,
        ])->assertCreated()->json();
        // Die Strecke des Kurzwegs ist gegenüber dem Angebot „aktuell“
        $this->assertSame(['aktuell'], $this->states($quote['journey_id']));
        $this->as()->deleteJson("/api/documents/{$quote['id']}")->assertOk();
        $this->assertSame(0, Journey::count());
    }

    public function test_reise_mit_entwurf_wird_samt_entwurf_geloescht(): void
    {
        $j = $this->journey();
        $this->section($j['id'])->assertCreated();
        $this->as()->postJson("/api/journeys/{$j['id']}/quote")->assertCreated();

        $this->as()->deleteJson("/api/journeys/{$j['id']}")->assertOk();

        $this->assertSame(0, Journey::count());
        $this->assertSame(0, Document::count());
        $this->assertSame(0, JourneySection::count());
    }

    public function test_reise_mit_festgeschriebenem_beleg_laesst_sich_nicht_loeschen(): void
    {
        $j = $this->journey(['customer_id' => $this->customer()->id]);
        $this->section($j['id'])->assertCreated();
        $created = $this->as()->postJson("/api/journeys/{$j['id']}/quote")->assertCreated()->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$created['id']}/finalize")->assertOk();

        $this->as()->deleteJson("/api/journeys/{$j['id']}")->assertUnprocessable()->assertJsonValidationErrors(['journey']);

        $this->assertSame(1, Journey::count());
        $this->assertSame(1, Document::count());
    }

    public function test_eingaben_und_rechte(): void
    {
        $j = $this->journey();
        $this->section($j['id'], ['vehicles' => []])->assertUnprocessable()->assertJsonValidationErrors(['vehicles']);
        $this->section($j['id'], ['kind' => 'quatsch'])->assertUnprocessable()->assertJsonValidationErrors(['kind']);
        $this->section($j['id'], ['tour' => null])->assertUnprocessable();   // keine Quelle
        $this->section($j['id'], ['trip_id' => '3f0e0f6e-7c8b-4c1a-9a53-0d2b1f5b7a11'])->assertUnprocessable();   // zwei Quellen und unbekannt
        $this->assertSame(0, JourneySection::count());

        // Lesen: Buchhaltung ja; Planen: nur Admin und Disponent
        $this->as(Role::Buchhaltung)->getJson("/api/journeys/{$j['id']}")->assertOk();
        $this->as(Role::Buchhaltung)->postJson('/api/journeys', ['title' => 'X'])->assertForbidden();
        $this->as(Role::Buchhaltung)->postJson("/api/journeys/{$j['id']}/sections", ['kind' => 'hinfahrt'])->assertForbidden();
        $this->as(Role::Buchhaltung)->postJson("/api/journeys/{$j['id']}/quote")->assertForbidden();
        $this->as(Role::Fahrer)->getJson("/api/journeys/{$j['id']}")->assertForbidden();
        $this->as(Role::Admin)->postJson('/api/journeys', ['title' => 'Admin-Reise'])->assertCreated();
    }
}
