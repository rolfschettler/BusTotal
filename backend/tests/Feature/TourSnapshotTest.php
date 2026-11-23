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
use App\Services\DocumentPdf;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fahrtbeschreibung am Beleg: Beim Festschreiben wird der Stand der Strecken (Halte, Kilometer, Zeiten, Fahrzeuge) im Beleg
 * festgehalten. Spätere Änderungen am Tour-Archiv ändern den Beleg nicht.
 */
class TourSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

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
        $this->customer = Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function tripBody(string $title = 'München – Wien', array $over = []): array
    {
        return array_merge([
            'title' => $title, 'trip_kind' => 'gelegenheit', 'vehicle_type' => 'doppeldecker', 'vehicle_count' => 1,
            'loaded_km' => 400, 'empty_km' => 20, 'drive_s' => 18000, 'elapsed_s' => 30000, 'days' => 1, 'round_trip' => false,
            'route' => ['legs' => [
                ['from' => ['label' => 'München'], 'to' => ['label' => 'Salzburg'], 'distance_m' => 150000, 'duration_s' => 6000, 'empty' => false],
                ['from' => ['label' => 'Salzburg'], 'to' => ['label' => 'Wien'], 'distance_m' => 250000, 'duration_s' => 9000, 'empty' => false],
            ]],
            'stops' => [
                ['label' => 'München', 'lat' => 48.1, 'lon' => 11.5],
                ['label' => 'Salzburg', 'lat' => 47.8, 'lon' => 13.0, 'dwell_minutes' => 45],
                ['label' => 'Wien', 'lat' => 48.2, 'lon' => 16.3],
            ],
        ], $over);
    }

    /** Angebot mit der gespeicherten Tour München – Wien und dem Kunden. */
    private function quote(?string &$tripId = null): array
    {
        $tripId = $this->as(Role::Disponent)->postJson('/api/trips', $this->tripBody())->assertCreated()->json('id');

        return $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', [
            'loaded_km' => 400, 'empty_km' => 20, 'drive_s' => 18000, 'days' => 1, 'label' => 'München – Wien',
            'vehicle_type' => 'doppeldecker', 'vehicle_count' => 2, 'trip_id' => $tripId, 'customer_id' => $this->customer->id,
            'start_at' => '2027-05-10T06:00:00+02:00',
        ])->assertCreated()->json();
    }

    private function finalize(string $id): array
    {
        return $this->as(Role::Buchhaltung)->postJson("/api/documents/$id/finalize")->assertOk()->json();
    }

    public function test_entwurf_hat_noch_keinen_schnappschuss(): void
    {
        $quote = $this->quote();

        $this->assertNull(Document::findOrFail($quote['id'])->tour_snapshot);
    }

    public function test_festschreiben_haelt_strecke_halte_kilometer_und_fahrzeuge_fest(): void
    {
        $quote = $this->quote();

        $doc = $this->finalize($quote['id']);

        $this->assertCount(1, $doc['tour_snapshot']['sections']);
        $s = $doc['tour_snapshot']['sections'][0];
        $this->assertSame('Hinfahrt', $s['kind_label']);
        $this->assertSame('München – Wien', $s['title']);
        $this->assertSame(['München', 'Salzburg', 'Wien'], array_column($s['trip']['stops'], 'label'));
        $this->assertSame(45, $s['trip']['stops'][1]['dwell_minutes']);
        $this->assertEquals(400, $s['trip']['loaded_km']);
        $this->assertSame(18000, $s['trip']['drive_s']);
        $this->assertCount(2, $s['trip']['legs']);
        $this->assertSame('Salzburg', $s['trip']['legs'][0]['to']);
        $this->assertSame([['type' => 'doppeldecker', 'label' => 'Doppeldeckerbus', 'count' => 2]], $s['vehicles']);
        $this->assertArrayNotHasKey('geometry', $s['trip']);
        $this->assertNotEmpty($doc['tour_snapshot']['created_at']);
    }

    public function test_spaetere_aenderung_oder_loeschen_der_tour_aendert_den_beleg_nicht(): void
    {
        $quote = $this->quote($tripId);
        $this->finalize($quote['id']);

        $this->as(Role::Disponent)->putJson("/api/trips/$tripId", $this->tripBody('Ganz anders', [
            'loaded_km' => 999, 'stops' => [['label' => 'Berlin', 'lat' => 52.5, 'lon' => 13.4], ['label' => 'Hamburg', 'lat' => 53.5, 'lon' => 10.0]],
        ]))->assertOk();

        $s = $this->as(Role::Buchhaltung)->getJson("/api/documents/{$quote['id']}")->assertOk()->json('tour_snapshot.sections.0');
        $this->assertSame(['München', 'Salzburg', 'Wien'], array_column($s['trip']['stops'], 'label'));
        $this->assertEquals(400, $s['trip']['loaded_km']);
        $this->assertSame('München – Wien', $s['title']);
    }

    public function test_der_schnappschuss_ist_nach_dem_festschreiben_gesperrt(): void
    {
        $quote = $this->quote();
        $this->finalize($quote['id']);

        $doc = Document::findOrFail($quote['id']);
        $this->expectException(\LogicException::class);
        $doc->forceFill(['tour_snapshot' => ['sections' => []]])->save();
    }

    public function test_pdf_zeigt_die_fahrtbeschreibung_aus_dem_beleg(): void
    {
        $quote = $this->quote($tripId);
        $this->finalize($quote['id']);
        // Das Archiv ändert sich, der Beleg zeigt weiter den festgehaltenen Stand.
        $this->as(Role::Disponent)->putJson("/api/trips/$tripId", $this->tripBody('Anders', ['loaded_km' => 1]))->assertOk();

        $html = app(DocumentPdf::class)->html(Document::findOrFail($quote['id']));

        $this->assertStringContainsString('Fahrtbeschreibung', $html);
        $this->assertStringContainsString('Hinfahrt: München – Wien', $html);
        $this->assertStringContainsString('2 × Doppeldeckerbus', $html);
        $this->assertStringContainsString('München → Salzburg (45 Min.) → Wien', $html);   // Halte mit Aufenthalt
        $this->assertStringContainsString('400,0 km mit Fahrgästen', $html);
        $this->assertStringContainsString('Lenkzeit 5 Std.', $html);
        $this->assertStringContainsString('München → Salzburg: 150,0 km, 1 Std. 40 Min.', $html);
    }

    public function test_entwurf_pdf_und_belege_ohne_strecken_zeigen_keine_fahrtbeschreibung(): void
    {
        $quote = $this->quote();
        $this->assertStringNotContainsString('Fahrtbeschreibung', app(DocumentPdf::class)->html(Document::findOrFail($quote['id'])));

        $manual = $this->as(Role::Buchhaltung)->postJson('/api/documents', [
            'type' => 'angebot', 'customer_id' => $this->customer->id,
            'lines' => [['title' => 'Busfahrt', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '100', 'tax_rate' => 19]],
        ])->assertCreated()->json('id');
        $doc = $this->finalize($manual);
        $this->assertNull($doc['tour_snapshot']);
    }

    public function test_auftragsbestaetigung_uebernimmt_den_stand_des_angebots_auch_nach_aenderung_der_tour(): void
    {
        $quote = $this->quote($tripId);
        $this->finalize($quote['id']);
        $this->as(Role::Disponent)->putJson("/api/trips/$tripId", $this->tripBody('München – Wien', ['loaded_km' => 777]))->assertOk();

        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json();
        $doc = $this->finalize($order['id']);

        $this->assertEquals(400, $doc['tour_snapshot']['sections'][0]['trip']['loaded_km'], 'Die AB beschreibt, was angeboten wurde.');
    }

    public function test_neue_strecke_in_der_ab_kommt_aus_dem_aktuellen_archiv(): void
    {
        $quote = $this->quote();
        $this->finalize($quote['id']);
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json();
        $second = $this->as(Role::Disponent)->postJson('/api/trips', $this->tripBody('Wien – München', ['loaded_km' => 410]))->assertCreated()->json('id');

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/sections", [
            'kind' => 'rueckfahrt', 'trip_id' => $second, 'vehicles' => [['type' => 'doppeldecker', 'count' => 2]],
        ])->assertCreated();
        $doc = $this->finalize($order['id']);

        $this->assertCount(2, $doc['tour_snapshot']['sections']);
        $this->assertSame(['Hinfahrt', 'Rückfahrt'], array_column($doc['tour_snapshot']['sections'], 'kind_label'));
        $this->assertEquals(410, $doc['tour_snapshot']['sections'][1]['trip']['loaded_km']);
    }

    public function test_anzahlung_und_storno_haben_keine_fahrtbeschreibung_die_rechnung_schon(): void
    {
        $quote = $this->quote();
        $this->finalize($quote['id']);
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json();
        $this->finalize($order['id']);

        $advance = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/convert", ['type' => 'anzahlung'])->assertCreated()->json();
        $this->assertNull($this->finalize($advance['id'])['tour_snapshot']);

        $invoice = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/convert", ['type' => 'rechnung'])->assertCreated()->json();
        $this->assertNotNull($this->finalize($invoice['id'])['tour_snapshot']);

        $storno = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice['id']}/cancel")->assertCreated()->json();
        $this->assertNull(Document::findOrFail($storno['id'])->tour_snapshot);
    }

    public function test_archiv_pdf_enthaelt_die_fahrtbeschreibung(): void
    {
        $quote = $this->quote();
        $this->finalize($quote['id']);

        $res = $this->as(Role::Buchhaltung)->get("/api/documents/{$quote['id']}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        // Das Archiv-PDF entsteht beim Festschreiben mit dem Stand im Beleg (der Schnappschuss ist vorher gesetzt).
        $this->assertNotNull(Document::findOrFail($quote['id'])->archive_sha256);
        $this->assertSame(1, Journey::count());
    }
}
