<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TripOrderStatus;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Journey;
use App\Models\PricingSetting;
use App\Models\TripOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripOrderPdf;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Reise absagen und die Absage zurücknehmen: Fahrtaufträge, Sperren, Hinweise zu Zahlungen. */
class JourneyCancelTest extends TestCase
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

    /** Reise mit festgeschriebenem Angebot (2 Busse). */
    private function offer(): array
    {
        $customer = Customer::factory()->create(['company_name' => 'Reisebüro', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
        $quote = $this->as(Role::Disponent)->postJson('/api/quotes/from-tour', [
            'loaded_km' => 100, 'empty_km' => 0, 'drive_s' => 3600, 'days' => 1, 'label' => 'München – Wien',
            'vehicle_type' => 'reisebus', 'vehicle_count' => 2, 'start_at' => '2027-05-10T06:00:00+02:00', 'customer_id' => $customer->id,
        ])->assertCreated()->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/finalize")->assertOk();

        return $quote;
    }

    /** Dazu die festgeschriebene Auftragsbestätigung, mit der die Fahrtaufträge entstehen. */
    private function confirmed(): array
    {
        $quote = $this->offer();
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order['id']}/finalize")->assertOk();

        return ['journey' => $quote['journey_id'], 'offer' => $quote['id'], 'confirmation' => $order['id']];
    }

    private function cancelJourney(string $id, array $over = [])
    {
        return $this->as(Role::Disponent)->postJson("/api/journeys/$id/cancel", $over + ['date' => now('Europe/Berlin')->toDateString(), 'reason' => 'Gruppe zu klein']);
    }

    public function test_rechte_und_pflichtangaben(): void
    {
        $id = $this->offer()['journey_id'];

        foreach ([Role::Buchhaltung, Role::Fahrer] as $role) {
            $this->as($role)->getJson("/api/journeys/$id/cancellation")->assertForbidden();
            $this->as($role)->postJson("/api/journeys/$id/cancel", [])->assertForbidden();
            $this->as($role)->postJson("/api/journeys/$id/reactivate")->assertForbidden();
        }
        $this->cancelJourney($id, ['reason' => '  '])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->cancelJourney($id, ['date' => now('Europe/Berlin')->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors(['date']);
        $this->as(Role::Admin)->postJson("/api/journeys/$id/reactivate")->assertUnprocessable()->assertJsonValidationErrors(['journey']);

        $this->cancelJourney($id, ['date' => '2026-01-05'])->assertOk()
            ->assertJsonPath('state', 'abgesagt')->assertJsonPath('cancelled_on', '2026-01-05')->assertJsonPath('cancel_reason', 'Gruppe zu klein');
        $this->cancelJourney($id)->assertUnprocessable()->assertJsonValidationErrors(['journey']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cancelled', 'auditable_id' => $id]);
    }

    public function test_fahrtauftraege_werden_mit_abgesagt_und_kommen_mit_der_ruecknahme_zurueck(): void
    {
        $ids = $this->confirmed();
        [$first, $second] = TripOrder::orderBy('bus_no')->get();
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$first->id}", ['external_company' => 'Bus Huber GmbH'])->assertOk();
        $number = $this->as(Role::Disponent)->postJson("/api/trip-orders/{$first->id}/issue")->assertOk()->json('number');

        $this->as(Role::Disponent)->getJson("/api/journeys/{$ids['journey']}/cancellation")->assertOk()
            ->assertJsonCount(2, 'orders')->assertJsonPath('orders.0.number', $number)->assertJsonPath('orders.0.drivers.0', 'Bus Huber GmbH')
            ->assertJsonPath('kept', 0)->assertJsonPath('paid_total', '0.00');

        $this->as(Role::Buchhaltung)->getJson("/api/journeys/{$ids['journey']}")->assertOk()
            ->assertJsonCount(2, 'trip_order_list')->assertJsonPath('trip_order_list.0.number', $number)->assertJsonPath('trip_order_list.0.external_company', 'Bus Huber GmbH')
            ->assertJsonPath('trip_order_list.1.bus_no', 2)->assertJsonPath('trip_order_list.1.status_label', 'Zu disponieren');

        $this->cancelJourney($ids['journey'])->assertOk()
            ->assertJsonPath('trip_orders.cancelled', 2)->assertJsonPath('trip_orders.to_plan', 0)->assertJsonPath('trip_orders.issued', 0)
            ->assertJsonPath('trip_orders.check', false);

        $this->as(Role::Disponent)->getJson("/api/trip-orders/{$first->id}")->assertOk()
            ->assertJsonPath('status_label', 'Abgesagt')->assertJsonPath('status', 'ausgegeben')->assertJsonCount(0, 'warnings');
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$second->id}", ['driver_note' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['assignment']);
        $this->as(Role::Disponent)->postJson("/api/trip-orders/{$first->id}/issue")->assertUnprocessable();
        $this->as(Role::Disponent)->getJson('/api/trip-orders?status=abgesagt')->assertJsonCount(2, 'data');
        $this->as(Role::Disponent)->getJson('/api/trip-orders?status=ausgegeben')->assertJsonCount(0, 'data');
        $this->assertStringContainsString('ABGESAGT', app(TripOrderPdf::class)->html($first->fresh()));

        $this->as(Role::Admin)->postJson("/api/journeys/{$ids['journey']}/reactivate")->assertOk()
            ->assertJsonPath('state', 'laufend')->assertJsonPath('cancelled_on', null)->assertJsonPath('trip_orders.cancelled', 0)->assertJsonPath('trip_orders.issued', 1);
        $this->as(Role::Disponent)->getJson("/api/trip-orders/{$first->id}")->assertJsonPath('status_label', 'Ausgegeben')->assertJsonPath('number', $number);
        $this->assertStringNotContainsString('ABGESAGT', app(TripOrderPdf::class)->html($first->fresh()));
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$second->id}", ['driver_note' => 'x'])->assertOk();
    }

    public function test_erledigte_fahrtauftraege_bleiben(): void
    {
        $ids = $this->confirmed();
        $done = TripOrder::orderBy('bus_no')->first();
        $done->forceFill(['status' => TripOrderStatus::Erledigt])->save();

        $this->as(Role::Disponent)->getJson("/api/journeys/{$ids['journey']}/cancellation")->assertJsonCount(1, 'orders')->assertJsonPath('kept', 1);
        $this->cancelJourney($ids['journey'])->assertOk()->assertJsonPath('trip_orders.cancelled', 1);
        $this->assertNull($done->fresh()->cancelled_at);
    }

    public function test_abgesagte_fahrt_belegt_fahrzeug_nicht_mehr(): void
    {
        $ids = $this->confirmed();
        $vehicle = Vehicle::factory()->create();
        $mine = TripOrder::orderBy('bus_no')->first();
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$mine->id}", ['vehicle_id' => $vehicle->id])->assertOk();
        $other = new TripOrder;
        $other->forceFill(['status' => TripOrderStatus::Entwurf, 'title' => 'Ulm', 'trip_kind' => 'gelegenheit', 'bus_no' => 1,
            'vehicle_id' => $vehicle->id, 'date_from' => '2027-05-10', 'date_to' => '2027-05-10'])->save();

        $codes = fn () => array_column($this->as(Role::Disponent)->getJson("/api/trip-orders/{$other->id}")->assertOk()->json('warnings'), 'code');
        $this->assertContains('vehicle_busy', $codes());
        $this->cancelJourney($ids['journey'])->assertOk();
        $this->assertNotContains('vehicle_busy', $codes());
    }

    public function test_abgesagte_reise_laesst_sich_nicht_weiter_planen(): void
    {
        $quote = $this->offer();
        $journeyId = $quote['journey_id'];
        $section = Journey::findOrFail($journeyId)->sections()->first();
        $this->cancelJourney($journeyId)->assertOk()->assertJsonPath('quote.can_create', false)->assertJsonPath('quote.can_sync', false);

        $this->as(Role::Disponent)->postJson("/api/journeys/$journeyId/sections", [
            'kind' => 'rueckfahrt', 'own' => ['title' => 'Zurück', 'loaded_km' => 50, 'drive_minutes' => 60, 'days' => 1], 'vehicles' => [['type' => 'reisebus', 'count' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['journey']);
        $this->as(Role::Disponent)->putJson("/api/journeys/$journeyId/sections/{$section->id}", ['title' => 'Neu'])->assertUnprocessable()->assertJsonValidationErrors(['journey']);
        $this->as(Role::Disponent)->deleteJson("/api/journeys/$journeyId/sections/{$section->id}")->assertUnprocessable()->assertJsonValidationErrors(['journey']);
        $this->as(Role::Disponent)->postJson("/api/journeys/$journeyId/quote")->assertUnprocessable()->assertJsonValidationErrors(['journey']);
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])
            ->assertUnprocessable()->assertJsonValidationErrors(['document']);

        // Name ändern und der Stand im Beleg gehen weiter
        $this->as(Role::Disponent)->putJson("/api/journeys/$journeyId", ['title' => 'Abgesagt: Wien'])->assertOk();
        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$quote['id']}")->assertJsonPath('journey.state', 'abgesagt');
    }

    public function test_entwurf_der_auftragsbestaetigung_laesst_sich_nach_der_absage_nicht_festschreiben(): void
    {
        $quote = $this->offer();
        $draft = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$quote['id']}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json();
        $this->cancelJourney($quote['journey_id'])->assertOk();

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$draft['id']}/finalize")->assertUnprocessable()->assertJsonValidationErrors(['document']);
        $this->assertSame(0, TripOrder::count());
        $this->assertDatabaseHas('documents', ['id' => $draft['id'], 'status' => 'entwurf', 'number' => null]);
    }

    public function test_vorschau_nennt_eingegangene_anzahlungen(): void
    {
        $ids = $this->confirmed();
        $advance = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$ids['confirmation']}/convert", ['type' => 'anzahlung'])->assertCreated()->json();
        $number = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$advance['id']}/finalize")->assertOk()->json('number');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$advance['id']}/payments", ['amount' => '100.00', 'paid_on' => now()->toDateString()])->assertCreated();

        $this->as(Role::Disponent)->getJson("/api/journeys/{$ids['journey']}/cancellation")->assertOk()
            ->assertJsonCount(1, 'payments')->assertJsonPath('payments.0.number', $number)->assertJsonPath('payments.0.paid', '100.00')->assertJsonPath('paid_total', '100.00');
    }
}
