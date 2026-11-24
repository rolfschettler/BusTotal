<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TripOrderStatus;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\TripOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripOrderPdf;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Etappe 2, Schritt 5: Fahrer-Fassung des Fahrtauftrags als PDF, ohne Preise, mit Erfassungsblatt. */
class TripOrderPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(SettingsSeeder::class);
        CompanySetting::current()->update(['name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457', 'city' => 'Hamburg']);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function route(int $stops = 3): array
    {
        $labels = array_map(fn ($i) => "Haltestelle $i, Musterstadt", range(1, $stops));

        return [
            'section_id' => 'x', 'kind' => 'hinfahrt', 'kind_label' => 'Hinfahrt', 'title' => 'München – Wien',
            'trip' => [
                'title' => 'München – Wien', 'trip_kind' => 'gelegenheit', 'loaded_km' => 400.4, 'empty_km' => 12.0, 'drive_s' => 18000,
                'elapsed_s' => 30000, 'days' => 2, 'round_trip' => true,
                'stops' => array_map(fn ($l, $i) => ['label' => $l, 'dwell_minutes' => $i % 2 ? 30 : 0, 'driver_free' => false], $labels, array_keys($labels)),
                'legs' => array_map(fn ($i) => ['from' => $labels[$i], 'to' => $labels[$i + 1], 'distance_m' => 50000, 'duration_s' => 3600, 'empty' => $i === 0], range(0, $stops - 2)),
            ],
        ];
    }

    private function order(array $over = [], int $stops = 3): TripOrder
    {
        $customer = Customer::factory()->create(['company_name' => 'Reisebüro Sonnenschein', 'last_name' => null, 'first_name' => null]);
        $order = new TripOrder;
        $order->forceFill(array_merge([
            'status' => TripOrderStatus::Disponiert, 'title' => 'München – Wien', 'trip_kind' => 'gelegenheit', 'vehicle_type' => 'reisebus',
            'bus_no' => 1, 'date_from' => '2027-05-10', 'date_to' => '2027-05-12', 'customer_id' => $customer->id, 'route' => $this->route($stops),
            'passengers' => 42, 'driver_note' => 'Treffpunkt 6 Uhr am Busbahnhof',
        ], $over))->save();

        return $order;
    }

    public function test_die_vorlage_zeigt_strecke_fahrzeug_fahrer_und_hinweise_aber_keine_preise(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'M-XY 123', 'seats' => 49]);
        $driver = Driver::factory()->create(['last_name' => 'Huber', 'first_name' => 'Anna']);
        $driver2 = Driver::factory()->create(['last_name' => 'Maier', 'first_name' => 'Karl']);
        $order = $this->order(['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'driver2_id' => $driver2->id]);

        $html = app(TripOrderPdf::class)->html($order);

        foreach (['Reisebüro Sonnenschein', '10.05.2027 – 12.05.2027', 'M-XY 123', '49 Sitzplätze', 'Huber, Anna', 'Maier, Karl', 'Treffpunkt 6 Uhr',
            'Haltestelle 1, Musterstadt', 'Haltestelle 2, Musterstadt', 'Leerfahrt', 'Erfassungsblatt', 'Grenzübertritte', 'Unterschrift Fahrer', 'NOCH NICHT AUSGEGEBEN'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        foreach (['€', 'Preis', 'Netto', 'MwSt', 'Betrag:'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strip_tags(str_replace('Betrag</th>', '', $html)), "Preisangabe: $forbidden");
        }
    }

    public function test_ausgegeben_zeigt_die_nummer_ohne_entwurfshinweis_und_fremdunternehmer_steht_im_pdf(): void
    {
        $order = $this->order(['number' => 'FA-2027-00007', 'status' => TripOrderStatus::Ausgegeben, 'external_company' => 'Bus Huber GmbH', 'external_plate' => 'M-HU 123']);

        $html = app(TripOrderPdf::class)->html($order);

        $this->assertStringContainsString('FA-2027-00007', $html);
        $this->assertStringContainsString('Bus Huber GmbH', $html);
        $this->assertStringNotContainsString('NOCH NICHT AUSGEGEBEN', $html);
    }

    public function test_strecke_ohne_tour_nur_mit_eigenen_angaben(): void
    {
        $order = $this->order(['route' => ['title' => 'Ausflug', 'own' => ['loaded_km' => 60.0, 'drive_s' => 9000, 'days' => 1]]]);

        $this->assertStringContainsString('60,0 km', app(TripOrderPdf::class)->html($order));
    }

    public function test_schnittstelle_liefert_pdf_protokolliert_und_prueft_rechte(): void
    {
        $order = $this->order();

        $res = $this->as(Role::Disponent)->get("/api/trip-orders/{$order->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertTrue(AuditLog::where('event', 'pdf_created')->where('auditable_id', $order->id)->exists());

        $this->as(Role::Buchhaltung)->get("/api/trip-orders/{$order->id}/pdf")->assertOk();
        $this->as(Role::Fahrer)->get("/api/trip-orders/{$order->id}/pdf")->assertForbidden();
    }

    // ---------- Seitenumbruch (braucht pdftotext, sonst übersprungen) ----------

    private function pdftotext(): ?string
    {
        foreach (['pdftotext', 'C:/msys64/mingw64/bin/pdftotext.exe'] as $bin) {
            $out = [];
            @exec(escapeshellarg($bin).' -v 2>&1', $out);
            if (str_contains(implode(' ', $out), 'pdftotext')) {
                return $bin;
            }
        }

        return null;
    }

    /** @return list<string> Text je Seite */
    private function pages(TripOrder $order): array
    {
        $file = tempnam(sys_get_temp_dir(), 'fa').'.pdf';
        file_put_contents($file, app(TripOrderPdf::class)->render($order));
        $text = (string) shell_exec(escapeshellarg($this->pdftotext()).' -enc UTF-8 -layout '.escapeshellarg($file).' - 2>&1');
        @unlink($file);
        $pages = explode("\f", $text);
        if (trim((string) end($pages)) === '') {
            array_pop($pages);
        }

        return $pages;
    }

    public function test_das_erfassungsblatt_steht_allein_auf_der_letzten_seite_bei_kurzer_und_langer_strecke(): void
    {
        if ($this->pdftotext() === null) {
            $this->markTestSkipped('pdftotext ist nicht installiert.');
        }

        foreach ([2, 12, 45] as $stops) {
            $pages = $this->pages($this->order(['number' => 'FA-2027-0000'.$stops], $stops));
            $total = count($pages);

            $this->assertGreaterThanOrEqual(2, $total, "$stops Halte");
            foreach ($pages as $i => $text) {
                $this->assertMatchesRegularExpression('/Seite '.($i + 1).' von '.$total.'\b/', $text, "$stops Halte, Seite ".($i + 1));
                $this->assertStringContainsString('Busreisen Muster GmbH', $text);
            }
            // Das Erfassungsblatt ist genau die letzte Seite und passt auf eine Seite
            $this->assertStringContainsString('Erfassungsblatt', $pages[$total - 1]);
            $this->assertStringContainsString('Unterschrift Fahrer', $pages[$total - 1]);
            $this->assertStringNotContainsString('Erfassungsblatt', implode('', array_slice($pages, 0, $total - 1)));
            // Kein Halt steht auf dem Erfassungsblatt und kein Ausfüllfeld auf den Seiten davor
            $this->assertStringNotContainsString('Grenzübertritte', implode('', array_slice($pages, 0, $total - 1)));
        }
    }
}
