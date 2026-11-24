<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TripOrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\TripOrder;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Etappe 2, Schritt 4: Schnittstelle /api/trip-orders. */
class TripOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function order(array $over = []): TripOrder
    {
        static $n = 0;
        $order = new TripOrder;
        $order->forceFill(array_merge([
            'status' => TripOrderStatus::Entwurf, 'title' => 'München – Wien', 'trip_kind' => 'gelegenheit',
            'vehicle_type' => 'reisebus', 'bus_no' => ++$n, 'date_from' => '2027-05-10', 'date_to' => '2027-05-12',
        ], $over))->save();

        return $order;
    }

    public function test_rechte(): void
    {
        $order = $this->order();

        $this->as(Role::Buchhaltung)->getJson('/api/trip-orders')->assertOk();
        $this->as(Role::Buchhaltung)->getJson("/api/trip-orders/{$order->id}")->assertOk();
        $this->as(Role::Buchhaltung)->putJson("/api/trip-orders/{$order->id}", ['passengers' => 5])->assertForbidden();
        $this->as(Role::Buchhaltung)->postJson("/api/trip-orders/{$order->id}/issue")->assertForbidden();
        $this->as(Role::Fahrer)->getJson('/api/trip-orders')->assertForbidden();
    }

    public function test_liste_sortiert_nach_fahrtbeginn_und_filtert(): void
    {
        $late = $this->order(['title' => 'Salzburg', 'date_from' => '2027-06-01', 'date_to' => '2027-06-01']);
        $early = $this->order(['title' => 'Wien', 'date_from' => '2027-05-01']);
        $undated = $this->order(['title' => 'Prag', 'date_from' => null, 'date_to' => null]);
        $done = $this->order(['title' => 'Linz', 'status' => TripOrderStatus::Disponiert]);

        $res = $this->as(Role::Disponent)->getJson('/api/trip-orders')->assertOk();
        $this->assertSame([$early->id, $done->id, $late->id, $undated->id], array_column($res->json('data'), 'id'));

        $this->as(Role::Disponent)->getJson('/api/trip-orders?status=disponiert')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $done->id);
        $this->as(Role::Disponent)->getJson('/api/trip-orders?status=unsinn')->assertUnprocessable();
    }

    public function test_suche_am_wortanfang_in_strecke_und_kunde(): void
    {
        $customer = Customer::factory()->create(['company_name' => 'Reisebüro Sonnenschein', 'last_name' => null, 'first_name' => null]);
        $mine = $this->order(['title' => 'Hamburg – Berlin', 'customer_id' => $customer->id]);
        $this->order(['title' => 'Ulm – Augsburg']);

        $ids = fn (string $q) => array_column($this->as(Role::Disponent)->getJson('/api/trip-orders?q='.urlencode($q))->assertOk()->json('data'), 'id');

        $this->assertSame([$mine->id], $ids('Berl'));          // zweites Wort der Strecke
        $this->assertSame([$mine->id], $ids('Sonnen'));        // Kunde
        $this->assertSame([], $ids('burg'));                   // nicht mitten im Wort
    }

    public function test_anzeige_nennt_hinweise_und_zeigt_keine_preise(): void
    {
        $vehicle = Vehicle::factory()->create(['hu_due_date' => '2020-01-01']);
        $order = $this->order(['vehicle_id' => $vehicle->id]);

        $res = $this->as(Role::Disponent)->getJson("/api/trip-orders/{$order->id}")->assertOk()
            ->assertJsonPath('status_label', 'Zu disponieren')
            ->assertJsonPath('vehicle_type_label', 'Reisebus')
            ->assertJsonPath('warnings.0.code', 'vehicle_deadline');
        $this->assertStringNotContainsString('price', json_encode($res->json()));
    }

    public function test_disponieren_ueber_die_schnittstelle(): void
    {
        $order = $this->order();
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();

        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'passengers' => 40, 'driver_note' => 'Treffpunkt 6 Uhr'])
            ->assertOk()->assertJsonPath('status', 'disponiert')->assertJsonPath('vehicle.id', $vehicle->id)->assertJsonPath('driver.id', $driver->id)
            ->assertJsonPath('passengers', 40);

        // Unmögliches und fehlerhafte Eingaben
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['driver2_id' => $driver->id])->assertUnprocessable()->assertJsonValidationErrors(['assignment']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['passengers' => 0])->assertUnprocessable()->assertJsonValidationErrors(['passengers']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['vehicle_id' => 'kein-uuid'])->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id']);

        // Leerer Fremdunternehmer zählt als nicht angegeben
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['external_company' => '  '])->assertOk()->assertJsonPath('external_company', null);
    }

    public function test_ausgeben_vergibt_fortlaufende_nummern_und_sperrt_aenderungen(): void
    {
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $first = $this->order(['status' => TripOrderStatus::Disponiert, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id]);
        $second = $this->order(['external_company' => 'Bus Huber GmbH', 'status' => TripOrderStatus::Disponiert]);
        $open = $this->order();

        // Nicht disponiert: keine Nummer, keine Lücke
        $this->as(Role::Disponent)->postJson("/api/trip-orders/{$open->id}/issue")->assertUnprocessable()->assertJsonValidationErrors(['assignment']);

        $year = now()->format('Y');
        $this->as(Role::Disponent)->postJson("/api/trip-orders/{$first->id}/issue")->assertOk()
            ->assertJsonPath('number', "FA-$year-00001")->assertJsonPath('status', 'ausgegeben');
        $this->as(Role::Disponent)->postJson("/api/trip-orders/{$second->id}/issue")->assertOk()->assertJsonPath('number', "FA-$year-00002");

        // doppelt ausgeben geht nicht
        $this->as(Role::Disponent)->postJson("/api/trip-orders/{$first->id}/issue")->assertUnprocessable();

        // Änderung an der Zuweisung nur mit Begründung
        $other = Driver::factory()->create();
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$first->id}", ['driver_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors(['assignment']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$first->id}", ['driver_id' => $other->id, 'reason' => 'Fahrer krank'])
            ->assertOk()->assertJsonPath('driver.id', $other->id)->assertJsonPath('status', 'ausgegeben');
    }
}
