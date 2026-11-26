<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Journey;
use App\Models\PricingSetting;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInvoiceDrafts;
use Tests\TestCase;

/** Reisevorhaben: entsteht automatisch mit dem ersten Angebot aus der Tour und bleibt über die ganze Belegkette dieselbe. */
class JourneyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInvoiceDrafts;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake();
        $this->seed(SettingsSeeder::class);
        PricingSetting::current()->update(['price_per_km' => 2, 'empty_km_percent' => 100, 'tax_rate' => 19]);
        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457',
            'city' => 'Hamburg', 'tax_number' => '12/345/67890', 'payment_term_days' => 14,
        ]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function tour(array $over = []): array
    {
        return array_merge([
            'loaded_km' => 400, 'empty_km' => 0, 'drive_s' => 18000, 'days' => 1, 'label' => 'München – Wien',
            'start_at' => '2027-05-10T06:00:00+02:00', 'end_at' => '2027-05-12T20:00:00+02:00',
        ], $over);
    }

    private function trip(): string
    {
        return $this->as(Role::Disponent)->postJson('/api/trips', [
            'title' => 'München – Wien', 'trip_kind' => 'gelegenheit', 'vehicle_count' => 1, 'loaded_km' => 400, 'empty_km' => 0,
            'drive_s' => 18000, 'elapsed_s' => 30000, 'days' => 1,
            'stops' => [['label' => 'München', 'lat' => 48.1, 'lon' => 11.5], ['label' => 'Wien', 'lat' => 48.2, 'lon' => 16.3]],
        ])->assertCreated()->json('id');
    }

    private function quote(array $over = []): array
    {
        return $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour($over))->assertCreated()->json();
    }

    private function customer(): Customer
    {
        return Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
    }

    private function lines(): array
    {
        return [['title' => 'Busfahrt', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '100', 'tax_rate' => 19]];
    }

    public function test_das_erste_angebot_aus_der_tour_legt_die_reise_an(): void
    {
        $tripId = $this->trip();
        $quote = $this->quote(['trip_id' => $tripId]);

        $journey = Journey::firstOrFail();
        $this->assertSame($journey->id, $quote['journey_id']);
        $this->assertSame('München – Wien', $journey->title);
        $this->assertSame('2027-05-10', $journey->date_from->toDateString());
        $this->assertSame('2027-05-12', $journey->date_to->toDateString());
        $this->assertNull($journey->customer_id);

        $section = $journey->sections()->firstOrFail();
        $this->assertSame('hinfahrt', $section->kind->value);
        $this->assertSame('München – Wien', $section->title, 'Die Strecke heißt wie ihre Tour.');
        $this->assertSame(1, $section->position);
        $this->assertSame($tripId, $section->trip_id);
    }

    public function test_erste_strecke_heisst_wie_die_tour_nicht_wie_das_label(): void
    {
        $tripId = $this->trip();
        $this->quote(['trip_id' => $tripId, 'label' => 'München Hauptbahnhof – Wien Hauptbahnhof']);

        $journey = Journey::firstOrFail();
        $this->assertSame('München Hauptbahnhof – Wien Hauptbahnhof', $journey->title);
        $this->assertSame('München – Wien', $journey->sections()->firstOrFail()->title);
    }

    public function test_reise_ohne_gespeicherte_tour_hat_eine_hinfahrt_ohne_tour(): void
    {
        $this->quote();

        $this->assertNull(Journey::firstOrFail()->sections()->firstOrFail()->trip_id);
    }

    public function test_unbekannte_tour_wird_abgelehnt_und_nichts_angelegt(): void
    {
        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour(['trip_id' => '3f0e0f6e-7c8b-4c1a-9a53-0d2b1f5b7a11']))
            ->assertUnprocessable()->assertJsonValidationErrors(['trip_id']);

        $this->assertDatabaseCount('journeys', 0);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_ohne_preise_entsteht_weder_beleg_noch_reise(): void
    {
        PricingSetting::current()->update(['price_per_km' => 0, 'hourly_rate' => 0, 'day_rate' => 0, 'overnight_rate' => 0, 'minimum_price' => 0]);

        $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $this->tour())->assertUnprocessable();

        $this->assertDatabaseCount('journeys', 0);
    }

    public function test_der_beleg_zeigt_seine_reise_mit_allen_belegen(): void
    {
        $quote = $this->quote();

        $this->as(Role::Disponent)->getJson("/api/documents/{$quote['id']}")->assertOk()
            ->assertJsonPath('journey.title', 'München – Wien')
            ->assertJsonPath('journey.date_from', '2027-05-10')
            ->assertJsonCount(1, 'journey.documents')
            ->assertJsonPath('journey.documents.0.type', 'angebot')
            ->assertJsonPath('journey.documents.0.status', 'entwurf');
    }

    public function test_von_hand_angelegter_beleg_hat_keine_reise(): void
    {
        $id = $this->as(Role::Buchhaltung)->postJson('/api/documents', ['type' => 'angebot', 'lines' => $this->lines()])
            ->assertCreated()->assertJsonPath('journey', null)->json('id');

        $this->assertNull(Document::findOrFail($id)->journey_id);
    }

    public function test_der_kunde_im_entwurf_gilt_auch_fuer_die_reise_und_der_zeitraum_bleibt(): void
    {
        $quote = $this->quote();
        $customer = $this->customer();

        $this->as(Role::Disponent)->putJson("/api/documents/{$quote['id']}", [
            'customer_id' => $customer->id, 'service_date_from' => '2027-05-11', 'lines' => $this->lines(),
        ])->assertOk();

        $journey = Journey::firstOrFail();
        $this->assertSame($customer->id, $journey->customer_id);
        $this->assertSame('2027-05-11', $journey->date_from->toDateString());
        $this->assertSame('2027-05-12', $journey->date_to->toDateString(), 'Das Ende der Tour geht nicht verloren, wenn der Beleg keins hat.');
    }

    public function test_folgebelege_und_storno_bleiben_in_derselben_reise(): void
    {
        $quote = $this->quote(['customer_id' => $this->customer()->id]);
        $journeyId = $quote['journey_id'];
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();

        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])
            ->assertCreated()->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/finalize")->assertOk();

        $invoice = $this->invoiceDraftFrom($order['id']);
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice['id']}/finalize")->assertOk();

        $storno = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice['id']}/cancel")->assertCreated()->json();

        $this->assertSame(4, Document::where('journey_id', $journeyId)->count());
        $this->assertDatabaseCount('journeys', 1);
        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$storno['id']}")
            ->assertJsonPath('journey.id', $journeyId)
            ->assertJsonPath('journey.documents.0.type', 'angebot')
            ->assertJsonPath('journey.documents.3.type', 'gutschrift')
            ->assertJsonPath('journey.documents.2.cancelled', true);
    }

    public function test_folgebeleg_aendert_die_reise_nicht(): void
    {
        $customer = $this->customer();
        $quote = $this->quote(['customer_id' => $customer->id]);
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json();

        $this->as(Role::Buchhaltung)->putJson("/api/documents/{$order['id']}", [
            'customer_id' => null, 'service_date_from' => '2030-01-01', 'lines' => $this->lines(),
        ])->assertOk();

        $journey = Journey::firstOrFail();
        $this->assertSame($customer->id, $journey->customer_id);
        $this->assertSame('2027-05-10', $journey->date_from->toDateString());
    }

    public function test_entwurf_loeschen_nimmt_die_leere_reise_mit(): void
    {
        $quote = $this->quote();
        $this->assertDatabaseCount('journeys', 1);

        $this->as(Role::Disponent)->deleteJson("/api/documents/{$quote['id']}")->assertOk();

        $this->assertDatabaseCount('journeys', 0);
        $this->assertDatabaseCount('journey_sections', 0);
    }

    public function test_geloeschter_entwurf_laesst_die_tour_im_archiv(): void
    {
        $tripId = $this->trip();
        $quote = $this->quote(['trip_id' => $tripId]);

        $this->as(Role::Disponent)->deleteJson("/api/documents/{$quote['id']}")->assertOk();

        $this->assertNotNull(Trip::find($tripId));
    }

    public function test_tour_mit_reise_laesst_sich_loeschen_die_strecke_behaelt_ihre_kopie(): void
    {
        $tripId = $this->trip();
        $this->quote(['trip_id' => $tripId]);
        $section = \App\Models\JourneySection::firstOrFail();
        $this->assertNotNull($section->tour_data);
        $kopie = $section->tour_data;

        $this->as(Role::Disponent)->deleteJson("/api/trips/$tripId")->assertNoContent();

        $section->refresh();
        $this->assertNull($section->trip_id);
        $this->assertSame($kopie, $section->tour_data);
    }

    public function test_angebot_aus_berechneter_tour_legt_nichts_im_archiv_an_und_behaelt_eine_eigene_kopie(): void
    {
        $this->quote([
            'tour' => [
                'title' => 'Ulm – Salzburg', 'trip_kind' => 'gelegenheit', 'loaded_km' => 210.5, 'empty_km' => 12.0, 'drive_s' => 9000,
                'elapsed_s' => 14000, 'days' => 1, 'round_trip' => false,
                'stops' => [['label' => 'Ulm', 'dwell_minutes' => 0, 'driver_free' => false], ['label' => 'Salzburg', 'dwell_minutes' => 30, 'driver_free' => true]],
                'legs' => [['from' => 'Ulm', 'to' => 'Salzburg', 'distance_m' => 210500, 'duration_s' => 9000, 'empty' => false]],
            ],
        ]);

        // Nichts im Tour-Archiv, die Strecke trägt ihre eigene Kopie ohne Verweis
        $this->assertDatabaseCount('trips', 0);
        $section = \App\Models\JourneySection::firstOrFail();
        $this->assertNull($section->trip_id);
        $this->assertSame('Ulm – Salzburg', $section->tour_data['title']);
        $this->assertSame(['Ulm', 'Salzburg'], array_column($section->tour_data['stops'], 'label'));
        $this->assertTrue($section->tour_data['stops'][1]['driver_free']);
        $this->assertSame(210.5, $section->tour_data['loaded_km']);
        $this->assertSame('Salzburg', $section->tour_data['legs'][0]['to']);
        $this->assertSame('Gelegenheitsverkehr', $section->tour_data['trip_kind_label']);
    }
}
