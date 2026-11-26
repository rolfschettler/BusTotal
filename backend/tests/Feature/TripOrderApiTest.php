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

        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'passengers' => 'ca. 40 Pers.', 'staging_time' => '06:00', 'driver_note' => 'Treffpunkt 6 Uhr'])
            ->assertOk()->assertJsonPath('status', 'disponiert')->assertJsonPath('vehicle.id', $vehicle->id)->assertJsonPath('driver.id', $driver->id)
            ->assertJsonPath('passengers', 'ca. 40 Pers.')->assertJsonPath('staging_time', '06:00');

        // Unmögliches und fehlerhafte Eingaben
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['driver2_id' => $driver->id])->assertUnprocessable()->assertJsonValidationErrors(['assignment']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['passengers' => str_repeat('x', 41)])->assertUnprocessable()->assertJsonValidationErrors(['passengers']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['staging_time' => '25:99'])->assertUnprocessable()->assertJsonValidationErrors(['staging_time']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['boardings' => [['place' => 'Ulm', 'time' => '6 Uhr']]])->assertUnprocessable()->assertJsonValidationErrors(['boardings.0.time']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['boardings' => [['place' => '', 'time' => '06:30']]])->assertUnprocessable()->assertJsonValidationErrors(['assignment']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['vehicle_id' => 'kein-uuid'])->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id']);

        // Leerer Fremdunternehmer zählt als nicht angegeben
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['external_company' => '  '])->assertOk()->assertJsonPath('external_company', null);
    }

    public function test_zustiegsstellen_werden_ersetzt_geordnet_und_nach_dem_ausgeben_nur_mit_begruendung_geaendert(): void
    {
        $order = $this->order(['external_company' => 'Bus Huber GmbH', 'status' => TripOrderStatus::Disponiert]);

        // Leere Zeilen entfallen, die Reihenfolge bleibt, Personenzahl ist Text
        $res = $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['boardings' => [
            ['place' => 'Ulm Hbf', 'time' => '06:30', 'passengers' => '5'],
            ['place' => '', 'time' => '', 'passengers' => ''],
            ['place' => 'Neu-Ulm, Bahnhof', 'time' => null, 'passengers' => 'ca. 8 Pers.', 'kind' => 'ausstieg'],
        ]])->assertOk();
        $this->assertSame(['Ulm Hbf', 'Neu-Ulm, Bahnhof'], array_column($res->json('boardings'), 'place'));
        // Ohne Angabe gilt Zustieg
        $this->assertSame(['zustieg', 'ausstieg'], array_column($res->json('boardings'), 'kind'));
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['boardings' => [['place' => 'X', 'kind' => 'umstieg']]])->assertUnprocessable()->assertJsonValidationErrors(['boardings.0.kind']);
        $this->assertSame(['06:30', null], array_column($res->json('boardings'), 'time'));
        $this->assertSame(['5', 'ca. 8 Pers.'], array_column($res->json('boardings'), 'passengers'));
        $this->as(Role::Buchhaltung)->getJson("/api/trip-orders/{$order->id}")->assertJsonCount(2, 'boardings');

        // Ohne den Schlüssel bleiben sie, mit leerer Liste entfallen sie
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['driver_note' => 'x'])->assertJsonCount(2, 'boardings');

        // Nach dem Ausgeben braucht die Änderung eine Begründung, gleiche Liste nicht
        $this->as(Role::Disponent)->postJson("/api/trip-orders/{$order->id}/issue")->assertOk();
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['boardings' => []])->assertUnprocessable()->assertJsonValidationErrors(['assignment']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['boardings' => [
            ['place' => 'Ulm Hbf', 'time' => '06:30', 'passengers' => '5'], ['place' => 'Neu-Ulm, Bahnhof', 'time' => null, 'passengers' => 'ca. 8 Pers.', 'kind' => 'ausstieg'],
        ]])->assertOk();
        // Nur die Art zu ändern ist eine Änderung und braucht ebenfalls eine Begründung
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['boardings' => [
            ['place' => 'Ulm Hbf', 'time' => '06:30', 'passengers' => '5'], ['place' => 'Neu-Ulm, Bahnhof', 'time' => null, 'passengers' => 'ca. 8 Pers.', 'kind' => 'zustieg'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['assignment']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['boardings' => [], 'reason' => 'Gruppe sagt ab'])->assertOk()->assertJsonCount(0, 'boardings');
    }

    public function test_ausgabe_zuruecknehmen_behaelt_die_nummer(): void
    {
        $order = $this->order(['external_company' => 'Bus Huber GmbH', 'status' => TripOrderStatus::Disponiert]);
        $url = "/api/trip-orders/{$order->id}";

        $this->as(Role::Disponent)->postJson("$url/revoke", ['reason' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['assignment']);
        $number = $this->as(Role::Disponent)->postJson("$url/issue")->assertOk()->json('number');
        $this->as(Role::Buchhaltung)->postJson("$url/revoke", ['reason' => 'x'])->assertForbidden();
        $this->as(Role::Disponent)->postJson("$url/revoke", ['reason' => '  '])->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $this->as(Role::Disponent)->postJson("$url/revoke", ['reason' => 'zu früh ausgegeben'])->assertOk()
            ->assertJsonPath('status', 'disponiert')->assertJsonPath('number', $number)->assertJsonPath('issued_at', null);
        $this->assertDatabaseHas('audit_logs', ['event' => 'issue_revoked', 'auditable_id' => $order->id]);
        $this->assertStringContainsString('AUSGABE ZURÜCKGENOMMEN', app(\App\Services\TripOrderPdf::class)->html($order->fresh()));
        // wieder frei änderbar, ohne Begründung
        $this->as(Role::Disponent)->putJson($url, ['external_company' => 'Bus Maier'])->assertOk();

        // erneut ausgeben: dieselbe Nummer, der nächste Auftrag bekommt die folgende
        $this->as(Role::Disponent)->postJson("$url/issue")->assertOk()->assertJsonPath('number', $number)->assertJsonPath('status', 'ausgegeben');
        $this->assertStringNotContainsString('ZURÜCKGENOMMEN', app(\App\Services\TripOrderPdf::class)->html($order->fresh()));
        $next = $this->order(['external_company' => 'Bus Huber GmbH', 'status' => TripOrderStatus::Disponiert]);
        $this->as(Role::Disponent)->postJson("/api/trip-orders/{$next->id}/issue")->assertOk()->assertJsonPath('number', 'FA-'.now()->format('Y').'-00002');

        // mit Nummer nie löschbar
        $this->as(Role::Disponent)->postJson("$url/revoke", ['reason' => 'nochmal'])->assertOk();
        $this->expectException(\LogicException::class);
        $order->fresh()->delete();
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
