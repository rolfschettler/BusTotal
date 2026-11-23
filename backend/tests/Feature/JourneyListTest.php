<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\PricingSetting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Reisen: Übersicht und Detail (nur lesen), Stand und Betrag aus den Belegen abgeleitet, Verwendung der Touren im Archiv. */
class JourneyListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake();
        $this->seed(SettingsSeeder::class);
        PricingSetting::current()->update(['price_per_km' => 2, 'empty_km_percent' => 100, 'tax_rate' => 19, 'hourly_rate' => 0, 'day_rate' => 0, 'overnight_rate' => 0, 'minimum_price' => 0]);
        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457',
            'city' => 'Hamburg', 'tax_number' => '12/345/67890', 'payment_term_days' => 14,
        ]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function quote(string $label, ?Customer $customer = null, ?string $tripId = null): array
    {
        $body = ['loaded_km' => 100, 'empty_km' => 0, 'drive_s' => 3600, 'days' => 1, 'label' => $label, 'vehicle_type' => 'doppeldecker', 'start_at' => '2027-05-10T06:00:00+02:00']
            + ($customer ? ['customer_id' => $customer->id] : []) + ($tripId ? ['trip_id' => $tripId] : []);

        return $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', $body)->assertCreated()->json();
    }

    private function customer(string $name): Customer
    {
        return Customer::factory()->create(['company_name' => $name, 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
    }

    public function test_rechte(): void
    {
        $this->getJson('/api/journeys')->assertUnauthorized();
        $quote = $this->quote('München – Wien');

        $this->as(Role::Fahrer)->getJson('/api/journeys')->assertForbidden();
        foreach ([Role::Admin, Role::Disponent, Role::Buchhaltung] as $role) {
            $this->as($role)->getJson('/api/journeys')->assertOk();
            $this->as($role)->getJson('/api/journeys/'.$quote['journey_id'])->assertOk();
        }
        // nur lesen
        $this->as(Role::Admin)->postJson('/api/journeys', ['title' => 'X'])->assertStatus(405);
        $this->as(Role::Admin)->deleteJson('/api/journeys/'.$quote['journey_id'])->assertStatus(405);
    }

    public function test_liste_neueste_zuerst_mit_kunde_stand_und_betrag(): void
    {
        $this->quote('Ulm – Bonn');
        $customer = $this->customer('Reisebüro Sonnenschein');
        $second = $this->quote('München – Wien', $customer);

        $res = $this->as(Role::Disponent)->getJson('/api/journeys')->assertOk()->assertJsonCount(2, 'data');

        $res->assertJsonPath('data.0.title', 'München – Wien')
            ->assertJsonPath('data.0.customer.company_name', 'Reisebüro Sonnenschein')
            ->assertJsonPath('data.0.stage', 'angebot')
            ->assertJsonPath('data.0.stage_document_id', $second['id'])
            ->assertJsonPath('data.0.amount', '238.00')   // 100 km × 2 € + 19 %
            ->assertJsonPath('data.0.document_count', 1)
            ->assertJsonPath('data.0.sections_count', 1)
            ->assertJsonPath('data.1.title', 'Ulm – Bonn')
            ->assertJsonPath('data.1.customer', null);
    }

    public function test_stand_folgt_dem_weitesten_gueltigen_beleg_und_ignoriert_storno_und_anzahlung(): void
    {
        $customer = $this->customer('Reisebüro');
        $quote = $this->quote('München – Wien', $customer);
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/finalize")->assertOk();

        $this->as(Role::Buchhaltung)->getJson('/api/journeys')->assertJsonPath('data.0.stage', 'auftragsbestaetigung')->assertJsonPath('data.0.document_count', 2);

        $advance = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/convert", ['type' => 'anzahlung'])->assertCreated()->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$advance['id']}/finalize")->assertOk();
        $this->as(Role::Buchhaltung)->getJson('/api/journeys')->assertJsonPath('data.0.stage', 'auftragsbestaetigung');   // Anzahlung zählt nicht als Stand

        $invoice = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/convert", ['type' => 'rechnung'])->assertCreated()->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice['id']}/finalize")->assertOk();
        $this->as(Role::Buchhaltung)->getJson('/api/journeys')->assertJsonPath('data.0.stage', 'rechnung')->assertJsonPath('data.0.stage_document_id', $invoice['id']);

        // Rechnung storniert: Der Stand fällt auf die Auftragsbestätigung zurück.
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice['id']}/cancel")->assertCreated();
        $this->as(Role::Buchhaltung)->getJson('/api/journeys')->assertJsonPath('data.0.stage', 'auftragsbestaetigung')->assertJsonPath('data.0.document_count', 5);
    }

    public function test_suche_trifft_titel_und_kunde_am_wortanfang(): void
    {
        $this->quote('München – Wien', $this->customer('Reisebüro Sonnenschein'));
        $this->quote('Ulm – Bonn', $this->customer('Verein Wanderlust'));

        $this->as(Role::Disponent)->getJson('/api/journeys?q=wien')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'München – Wien');
        $this->as(Role::Disponent)->getJson('/api/journeys?q=sonnen')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'München – Wien');
        $this->as(Role::Disponent)->getJson('/api/journeys?q=bonn')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Ulm – Bonn');
        $this->as(Role::Disponent)->getJson('/api/journeys?q=ünchen')->assertJsonCount(0, 'data');
        $this->as(Role::Disponent)->getJson('/api/journeys?q=100%25')->assertJsonCount(0, 'data');
    }

    public function test_detail_mit_strecken_fahrzeugen_tour_und_belegen(): void
    {
        $tripId = $this->as(Role::Disponent)->postJson('/api/trips', [
            'title' => 'München – Wien', 'trip_kind' => 'gelegenheit', 'vehicle_count' => 1, 'loaded_km' => 400, 'empty_km' => 0, 'drive_s' => 18000, 'elapsed_s' => 30000, 'days' => 2,
            'stops' => [['label' => 'München', 'lat' => 48.1, 'lon' => 11.5], ['label' => 'Wien', 'lat' => 48.2, 'lon' => 16.3]],
        ])->assertCreated()->json('id');
        $customer = $this->customer('Reisebüro Sonnenschein');
        $quote = $this->quote('München – Wien', $customer, $tripId);

        $this->as(Role::Buchhaltung)->getJson('/api/journeys/'.$quote['journey_id'])->assertOk()
            ->assertJsonPath('title', 'München – Wien')
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('sections.0.kind_label', 'Hinfahrt')
            ->assertJsonPath('sections.0.vehicles.0.label', 'Doppeldeckerbus')
            ->assertJsonPath('sections.0.trip.id', $tripId)
            ->assertJsonPath('sections.0.trip.from', 'München')
            ->assertJsonPath('sections.0.trip.to', 'Wien')
            ->assertJsonPath('documents.0.id', $quote['id'])
            ->assertJsonPath('documents.0.gross_total', '238.00')
            ->assertJsonPath('stage', 'angebot')
            ->assertJsonPath('amount', '238.00');

        $this->as(Role::Disponent)->getJson('/api/journeys/3f0e0f6e-7c8b-4c1a-9a53-0d2b1f5b7a11')->assertNotFound();
    }

    public function test_tour_archiv_zaehlt_die_verwendung_in_reisen(): void
    {
        $tripId = $this->as(Role::Disponent)->postJson('/api/trips', [
            'title' => 'München – Wien', 'trip_kind' => 'gelegenheit', 'vehicle_count' => 1, 'loaded_km' => 100, 'empty_km' => 0, 'drive_s' => 3600, 'elapsed_s' => 7200, 'days' => 1,
            'stops' => [['label' => 'München', 'lat' => 48.1, 'lon' => 11.5], ['label' => 'Wien', 'lat' => 48.2, 'lon' => 16.3]],
        ])->assertCreated()->json('id');

        $this->as(Role::Disponent)->getJson('/api/trips')->assertJsonPath('data.0.sections_count', 0);
        $this->quote('München – Wien', null, $tripId);
        $this->as(Role::Disponent)->getJson('/api/trips')->assertJsonPath('data.0.sections_count', 1);
    }
}
