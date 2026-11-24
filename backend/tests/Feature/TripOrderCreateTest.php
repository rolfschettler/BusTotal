<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Driver;
use App\Models\NumberRange;
use App\Models\PricingSetting;
use App\Models\TripOrder;
use App\Models\User;
use App\Services\TripOrderService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

/**
 * Etappe 2, Schritt 1 und 2: Mit der festgeschriebenen Auftragsbestätigung entsteht je Strecke und Bus ein Fahrtauftrag als Entwurf.
 */
class TripOrderCreateTest extends TestCase
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

    /** Festgeschriebenes Angebot: Hinfahrt mit 1 Doppeldecker, dazu eine Fahrt vor Ort mit 1 Doppeldecker und 2 Kleinbussen. */
    private function quote(): array
    {
        $customer = Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
        $quote = $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', [
            'loaded_km' => 400, 'empty_km' => 0, 'drive_s' => 18000, 'days' => 1, 'label' => 'München – Wien',
            'vehicle_type' => 'doppeldecker', 'vehicle_count' => 1,
        ])->assertCreated()->json();

        $lines = $this->as(Role::Disponent)->postJson("/api/documents/{$quote['id']}/sections", [
            'kind' => 'vor_ort',
            'own' => ['title' => 'Ausflug Ossiacher See', 'loaded_km' => 60, 'drive_minutes' => 150, 'days' => 1],
            'vehicles' => [['type' => 'doppeldecker', 'count' => 1], ['type' => 'minibus', 'count' => 2]],
        ])->assertCreated()->json('lines');
        $lines = array_map(fn (array $l) => array_intersect_key($l, array_flip(['title', 'description', 'quantity', 'unit', 'trip_kind', 'unit_price', 'tax_rate', 'tax_note', 'journey_section_id', 'vehicle_type'])), $lines);
        $this->as(Role::Disponent)->putJson("/api/documents/{$quote['id']}", ['customer_id' => $customer->id, 'service_date_from' => '2027-05-10', 'service_date_to' => '2027-05-12', 'lines' => $lines])->assertOk();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();

        return $quote;
    }

    private function confirmation(array $quote, bool $finalize = true): array
    {
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json();
        if ($finalize) {
            $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/finalize")->assertOk();
        }

        return $order;
    }

    public function test_die_migration_hat_den_nummernkreis_angelegt(): void
    {
        $this->assertSame('FA', NumberRange::where('type', 'fahrtauftrag')->firstOrFail()->prefix);
    }

    public function test_angebot_und_entwurf_der_ab_erzeugen_keine_fahrtauftraege(): void
    {
        $quote = $this->quote();
        $this->assertSame(0, TripOrder::count());

        $this->confirmation($quote, false);
        $this->assertSame(0, TripOrder::count());
    }

    public function test_die_festgeschriebene_ab_erzeugt_je_strecke_und_bus_einen_entwurf(): void
    {
        $quote = $this->quote();
        $ab = $this->confirmation($quote);

        // Hinfahrt: 1 Doppeldecker; Fahrt vor Ort: 1 Doppeldecker und 2 Kleinbusse
        $this->assertSame(4, TripOrder::count());
        $orders = TripOrder::orderBy('created_at')->get();

        $hin = $orders->where('title', 'München – Wien')->values();
        $this->assertCount(1, $hin);
        $this->assertSame('entwurf', $hin[0]->status->value);
        $this->assertNull($hin[0]->number);
        $this->assertNull($hin[0]->vehicle_id);
        $this->assertNull($hin[0]->driver_id);
        $this->assertSame('doppeldecker', $hin[0]->vehicle_type->value);
        $this->assertSame($ab['id'], $hin[0]->document_id);
        $this->assertSame($ab['customer_id'], $hin[0]->customer_id);
        $this->assertSame('2027-05-10', $hin[0]->date_from->toDateString());
        $this->assertSame('2027-05-12', $hin[0]->date_to->toDateString());
        $this->assertSame('offen', $hin[0]->billing_status);

        $ort = $orders->where('title', 'Ausflug Ossiacher See');
        $this->assertSame(['doppeldecker' => 1, 'minibus' => 2], $ort->countBy(fn ($o) => $o->vehicle_type->value)->all());
        $this->assertSame([1, 2], $ort->where('vehicle_type.value', 'minibus')->pluck('bus_no')->sort()->values()->all());

        // Die Strecke steht ohne Preise und ohne Fahrzeugliste im Auftrag
        $this->assertSame(60, $ort->first()->route['own']['loaded_km']);
        $this->assertArrayNotHasKey('vehicles', $ort->first()->route);
    }

    public function test_erneutes_erzeugen_legt_nichts_doppelt_an(): void
    {
        $ab = $this->confirmation($this->quote());

        $this->assertSame([], app(TripOrderService::class)->createFromConfirmation(Document::findOrFail($ab['id'])));
        $this->assertSame(4, TripOrder::count());
    }

    public function test_die_reise_zeigt_die_fahrtauftraege_und_meldet_abweichungen(): void
    {
        $quote = $this->quote();
        $journeyId = Document::findOrFail($quote['id'])->journey_id;

        // Nur Angebot: nichts zu erwarten
        $this->as(Role::Disponent)->getJson("/api/journeys/$journeyId")->assertOk()
            ->assertJsonPath('trip_orders', ['count' => 0, 'to_plan' => 0, 'planned' => 0, 'issued' => 0, 'expected' => null, 'check' => false]);

        $ab = $this->confirmation($quote);
        $this->as(Role::Disponent)->getJson("/api/journeys/$journeyId")
            ->assertJsonPath('trip_orders.count', 4)->assertJsonPath('trip_orders.to_plan', 4)->assertJsonPath('trip_orders.expected', 4)->assertJsonPath('trip_orders.check', false);
        // dieselbe Zusammenfassung im Beleg (Reise-Leiste)
        $this->as(Role::Disponent)->getJson("/api/documents/{$ab['id']}")->assertJsonPath('journey.trip_orders.count', 4)->assertJsonPath('journey.trip_orders.check', false);

        // Ein Auftrag fehlt oder ist überzählig: Hinweis „prüfen“
        TripOrder::firstOrFail()->delete();
        $this->as(Role::Disponent)->getJson("/api/journeys/$journeyId")->assertJsonPath('trip_orders.count', 3)->assertJsonPath('trip_orders.check', true);
    }

    public function test_fahrerauswahl_nur_fuer_admin_und_disponent(): void
    {
        Driver::factory()->create(['last_name' => 'Huber', 'first_name' => 'Anna', 'license_classes' => ['D']]);

        $this->as(Role::Disponent)->getJson('/api/drivers/choices')->assertOk()
            ->assertJsonPath('0.display_name', 'Huber, Anna')->assertJsonPath('0.license_classes', ['D']);
        $this->as(Role::Buchhaltung)->getJson('/api/drivers/choices')->assertForbidden();
    }

    public function test_eine_rechnung_erzeugt_keine_fahrtauftraege(): void
    {
        $quote = $this->quote();
        $this->confirmation($quote);
        TripOrder::query()->delete();

        $invoice = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'rechnung'])->assertCreated()->json();
        $lines = array_map(fn (array $l) => array_intersect_key($l, array_flip(['title', 'description', 'quantity', 'unit', 'trip_kind', 'unit_price', 'tax_rate', 'tax_note', 'journey_section_id', 'vehicle_type'])), $invoice['lines']);
        $this->as(Role::Buchhaltung)->putJson("/api/documents/{$invoice['id']}", ['service_date_from' => '2027-05-10', 'lines' => $lines])->assertOk();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice['id']}/finalize")->assertOk();

        $this->assertSame(0, TripOrder::count());
    }

    public function test_ausgegebene_und_abgerechnete_auftraege_sind_im_modell_gesperrt(): void
    {
        $this->confirmation($this->quote());
        $order = TripOrder::firstOrFail();

        $order->forceFill(['number' => 'FA-2027-00001'])->save();
        try {
            $order->delete();
            $this->fail('Ein ausgegebener Fahrtauftrag darf nicht gelöscht werden.');
        } catch (LogicException) {
            $this->assertDatabaseHas('trip_orders', ['id' => $order->id]);
        }

        $order->forceFill(['billing_status' => 'abgerechnet'])->save();
        $this->expectException(LogicException::class);
        $order->fill(['driver_note' => 'zu spät'])->save();
    }
}
