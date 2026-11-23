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

    private function add(string $docId, array $over = [], Role $role = Role::Disponent)
    {
        // Die Tour zuerst anlegen: Sie wird als Disponent gespeichert und darf den angemeldeten Benutzer des Aufrufs nicht überschreiben.
        $body = array_merge(['kind' => 'vor_ort', 'trip_id' => $over['trip_id'] ?? $this->trip(), 'vehicles' => [['type' => 'doppeldecker', 'count' => 1]]], $over);

        return $this->as($role)->postJson("/api/documents/$docId/sections", $body);
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
            ->assertCreated();

        // erste Strecke: 400 km × 2 € = 800 €; neue Strecke: 100 km × 2 € = 200 € (Doppeldecker) und 2 × 100 km × 1 € = 200 € (Kleinbus)
        $res->assertJsonCount(3, 'lines')->assertJsonPath('net_total', '1200.00');
        $this->assertSame(['doppeldecker', 'doppeldecker', 'minibus'], array_column($res->json('lines'), 'vehicle_type'));
        $this->assertSame('Fahrten vor Ort: Wien – Wien Rundfahrt · 2 × Minibus / Kleinbus', $res->json('lines.2.description'));

        $journey = Journey::firstOrFail();
        $this->assertSame(2, $journey->sections()->count());
        $second = $journey->sections()->where('position', 2)->with('vehicles')->firstOrFail();
        $this->assertSame('vor_ort', $second->kind->value);
        $this->assertSame(2, $second->vehicles->count());
        $this->assertSame($second->id, $res->json('lines.1.journey_section_id'));
        $res->assertJsonPath('journey.sections.1.vehicles.1.count', 2);
    }

    public function test_die_zuordnung_ueberlebt_speichern_und_folgebelege(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
        $quote = $this->quote();
        $added = $this->add($quote['id'])->assertCreated()->json();

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

    public function test_fehlende_preise_legen_keinen_halben_abschnitt_an(): void
    {
        $quote = $this->quote();
        $trip = $this->trip();
        PricingSetting::current()->update(['price_per_km' => 0]);
        PricingVehicleRate::query()->delete();

        $this->add($quote['id'], ['trip_id' => $trip])->assertUnprocessable()->assertJsonValidationErrors(['pricing']);

        $this->assertSame(1, JourneySection::count());
        $this->assertCount(1, $this->as(Role::Disponent)->getJson("/api/documents/{$quote['id']}")->json('lines'));
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

    public function test_nur_im_entwurf_und_nur_mit_reise(): void
    {
        $quote = $this->quote();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertUnprocessable();   // ohne Kunde nicht festschreibbar
        $customer = Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
        $this->as(Role::Disponent)->putJson("/api/documents/{$quote['id']}", ['customer_id' => $customer->id, 'service_date_from' => '2027-05-10', 'lines' => $this->as(Role::Disponent)->getJson("/api/documents/{$quote['id']}")->json('lines')])->assertOk();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();

        $this->add($quote['id'])->assertUnprocessable()->assertJsonValidationErrors(['section']);

        $manual = $this->as(Role::Buchhaltung)->postJson('/api/documents', [
            'type' => 'angebot', 'lines' => [['title' => 'Busfahrt', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '100', 'tax_rate' => 19]],
        ])->assertCreated()->json('id');
        $this->add($manual)->assertUnprocessable()->assertJsonValidationErrors(['section']);
    }

    public function test_strecke_entfernen(): void
    {
        $quote = $this->quote();
        $added = $this->add($quote['id'])->assertCreated()->json();
        $sectionId = $added['lines'][1]['journey_section_id'];

        $res = $this->as(Role::Disponent)->deleteJson("/api/documents/{$quote['id']}/sections/$sectionId")->assertOk();

        $res->assertJsonCount(1, 'lines')->assertJsonPath('net_total', '800.00');
        $this->assertSame(1, JourneySection::count());
        $this->assertDatabaseCount('journey_section_vehicles', 1);
    }

    public function test_die_erste_strecke_bleibt_und_fremde_strecken_gehen_nicht(): void
    {
        $quote = $this->quote();
        $first = Journey::firstOrFail()->sections()->firstOrFail();

        $this->as(Role::Disponent)->deleteJson("/api/documents/{$quote['id']}/sections/{$first->id}")->assertUnprocessable()->assertJsonValidationErrors(['section']);

        $other = $this->quote();
        $foreign = Journey::findOrFail($other['journey_id'])->sections()->firstOrFail();
        $this->as(Role::Disponent)->deleteJson("/api/documents/{$quote['id']}/sections/{$foreign->id}")->assertUnprocessable();
        $this->assertSame(2, JourneySection::count());
    }

    public function test_entwurf_loeschen_loescht_abschnitte_und_fahrzeugbedarf_mit(): void
    {
        $quote = $this->quote();
        $this->add($quote['id'])->assertCreated();

        $this->as(Role::Disponent)->deleteJson("/api/documents/{$quote['id']}")->assertOk();

        $this->assertSame(0, Document::count());
        $this->assertSame(0, Journey::count());
        $this->assertDatabaseCount('journey_sections', 0);
        $this->assertDatabaseCount('journey_section_vehicles', 0);
    }

    public function test_rechte(): void
    {
        $quote = $this->quote();
        $trip = $this->trip();

        $this->add($quote['id'], ['trip_id' => $trip], Role::Fahrer)->assertForbidden();
        $this->add($quote['id'], ['trip_id' => $trip], Role::Buchhaltung)->assertCreated();
    }
}
