<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TripOrderStatus;
use App\Models\TripOrder;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Etappe 3: Rückerfassung eines Fahrtauftrags. */
class TripReportTest extends TestCase
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
            'status' => TripOrderStatus::Disponiert, 'title' => 'München – Wien', 'trip_kind' => 'gelegenheit', 'vehicle_type' => 'reisebus',
            'bus_no' => ++$n, 'date_from' => '2020-05-10', 'date_to' => '2020-05-12',
            'route' => ['trip' => ['loaded_km' => 500, 'empty_km' => 60, 'days' => 3,
                'stops' => [['label' => 'Ulm, Bayern'], ['label' => 'Wien']],
                'legs' => [['from' => 'Hof, Ort', 'to' => 'Ulm', 'empty' => true], ['from' => 'Ulm', 'to' => 'Wien', 'empty' => false], ['from' => 'Wien', 'to' => 'Hof', 'empty' => true]]]],
        ], $over))->save();

        return $order;
    }

    /** Die vorbelegten Zeilen mit den Kilometerständen der ersten und letzten Zeile. */
    private function body(TripOrder $order, ?int $start = 1000, ?int $end = 1560, array $over = []): array
    {
        $times = $this->as(Role::Disponent)->getJson($this->url($order))->assertOk()->json('report.times');
        $times[0]['km'] = $start;
        $times[count($times) - 1]['km'] = $end;

        return array_merge(['times' => $times, 'borders' => [], 'extras' => [], 'costs' => []], $over);
    }

    private function url(TripOrder $order, string $suffix = ''): string
    {
        return "/api/trip-orders/{$order->id}/report$suffix";
    }

    public function test_rechte(): void
    {
        $order = $this->order();
        $this->getJson($this->url($order))->assertUnauthorized();
        $this->as(Role::Fahrer)->getJson($this->url($order))->assertForbidden();
        $this->as(Role::Fahrer)->postJson($this->url($order, '/complete'))->assertForbidden();
        $this->as(Role::Buchhaltung)->putJson($this->url($order), $this->body($order))->assertOk();
    }

    public function test_zeilen_sind_wie_auf_dem_blatt_vorbelegt(): void
    {
        $res = $this->as(Role::Disponent)->getJson($this->url($this->order()))->assertOk()
            ->assertJsonPath('report.saved', false)->assertJsonPath('report.planned_km', 560)->assertJsonPath('report.editable', true)
            ->assertJsonPath('choices.default_tax_rate', 19);

        $times = $res->json('report.times');
        $this->assertSame(['Abfahrt Betriebshof', 'Abfahrt erster Halt', 'Ankunft Ziel', 'Abfahrt Rückreise', 'Ende am Betriebshof'], array_column($times, 'event'));
        $this->assertSame([null, 'betriebshof', 'befoerderung', 'befoerderung', 'betriebshof'], array_column($times, 'leg'));
        $this->assertSame('2020-05-10', $times[0]['date']);
        $this->assertSame('Hof', $times[0]['place']);

        // ohne Leerfahrt: keine Zeile zum Betriebshof, die letzte Zeile ist das Ende der Fahrt
        $own = $this->order(['route' => ['own' => ['loaded_km' => 40, 'days' => 1]]]);
        $rows = $this->as(Role::Disponent)->getJson($this->url($own))->json('report.times');
        $this->assertSame(['Abfahrt erster Halt', 'Ankunft Ziel', 'Abfahrt Rückreise', 'Ende der Fahrt'], array_column($rows, 'event'));
    }

    public function test_speichern_und_erledigen_vergibt_die_nummer_und_sperrt(): void
    {
        $order = $this->order();
        $this->as(Role::Disponent)->postJson($this->url($order, '/complete'))->assertUnprocessable()->assertJsonValidationErrors(['report']);

        $this->as(Role::Disponent)->putJson($this->url($order), $this->body($order, 1000, null))->assertOk()
            ->assertJsonPath('order.status', 'disponiert')->assertJsonPath('report.missing.0', 'km-Stand am Ende (letzte Zeile)');
        $this->as(Role::Disponent)->postJson($this->url($order, '/complete'))->assertUnprocessable();

        $this->as(Role::Disponent)->putJson($this->url($order), $this->body($order, 1000, 1560, [
            'actual_passengers' => '42', 'report_notes' => 'Stau',
            'extras' => [['title' => 'Sonderfahrt', 'quantity' => '2 Std.', 'place' => 'Wien', 'amount' => 120, 'payment' => 'bar', 'tax_rate' => 19]],
            'costs' => [['category' => 'maut', 'amount' => 45.5, 'receipt_no' => 'M1', 'travel_service' => false, 'charge_customer' => true]],
        ]))->assertOk()->assertJsonPath('report.missing', [])->assertJsonPath('report.warnings', [])
            ->assertJsonPath('report.extras.0.amount', '120.00')->assertJsonPath('report.costs.0.category', 'maut')->assertJsonPath('report.actual_passengers', '42');
        $this->assertDatabaseHas('audit_logs', ['event' => 'report_saved', 'auditable_id' => $order->id]);

        $year = now()->format('Y');
        $this->as(Role::Buchhaltung)->postJson($this->url($order, '/complete'))->assertOk()
            ->assertJsonPath('order.status', 'erledigt')->assertJsonPath('order.number', "FA-$year-00001")->assertJsonPath('report.editable', false);
        $this->as(Role::Disponent)->putJson($this->url($order), $this->body($order))->assertUnprocessable()->assertJsonValidationErrors(['report']);
        $this->as(Role::Disponent)->putJson("/api/trip-orders/{$order->id}", ['driver_note' => 'x'])->assertUnprocessable();
    }

    public function test_erledigt_zuruecknehmen(): void
    {
        $order = $this->order();
        $this->as(Role::Disponent)->putJson($this->url($order), $this->body($order))->assertOk();
        $this->as(Role::Disponent)->postJson($this->url($order, '/reopen'), ['reason' => 'x'])->assertUnprocessable();
        $number = $this->as(Role::Disponent)->postJson($this->url($order, '/complete'))->assertOk()->json('order.number');

        $this->as(Role::Disponent)->postJson($this->url($order, '/reopen'), [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->as(Role::Disponent)->postJson($this->url($order, '/reopen'), ['reason' => 'km falsch'])->assertOk()
            ->assertJsonPath('order.status', 'ausgegeben')->assertJsonPath('order.number', $number)->assertJsonPath('report.times.0.km', 1000);
        $this->assertDatabaseHas('audit_logs', ['event' => 'report_reopened', 'auditable_id' => $order->id]);
        $this->as(Role::Disponent)->postJson($this->url($order, '/complete'))->assertOk()->assertJsonPath('order.number', $number);
    }

    public function test_unmoegliches_wird_abgelehnt(): void
    {
        $order = $this->order();
        $this->as(Role::Disponent)->putJson($this->url($order), $this->body($order, 2000, 1000))->assertUnprocessable()->assertJsonValidationErrors(['report']);
        $border = ['country' => 'Österreich', 'km_in' => 1200, 'km_out' => 1100, 'source' => 'fahrer'];
        $this->as(Role::Disponent)->putJson($this->url($order), $this->body($order, 1000, 1560, ['borders' => [$border]]))->assertUnprocessable();
        $this->assertSame(0, $order->times()->count());

        $future = $this->order(['date_from' => '2099-01-01', 'date_to' => '2099-01-02']);
        $this->as(Role::Disponent)->putJson($this->url($future), $this->body($future))->assertOk();
        $this->as(Role::Disponent)->postJson($this->url($future, '/complete'))->assertUnprocessable();

        foreach ([['status' => TripOrderStatus::Entwurf], ['cancelled_at' => now()]] as $over) {
            $closed = $this->order($over);
            $this->as(Role::Disponent)->putJson($this->url($closed), ['times' => [], 'borders' => [], 'extras' => [], 'costs' => []])->assertUnprocessable();
        }
    }

    public function test_unvollstaendige_grenzzeile_verhindert_erledigt_und_fremdunternehmer_braucht_keine_km(): void
    {
        $order = $this->order();
        $this->as(Role::Disponent)->putJson($this->url($order), $this->body($order, 1000, 1560, [
            'borders' => [['country' => 'Österreich', 'km_in' => 1200, 'km_out' => null, 'source' => 'fahrer']],
        ]))->assertOk();
        $this->as(Role::Disponent)->postJson($this->url($order, '/complete'))->assertUnprocessable();

        $external = $this->order(['external_company' => 'Bus Huber']);
        $this->as(Role::Disponent)->putJson($this->url($external), $this->body($external, null, null))->assertOk()->assertJsonPath('report.missing', []);
        $this->as(Role::Disponent)->postJson($this->url($external, '/complete'))->assertOk();
    }

    public function test_hinweise_sperren_nicht(): void
    {
        $vehicle = Vehicle::factory()->create();
        $first = $this->order(['vehicle_id' => $vehicle->id, 'date_from' => '2020-04-01', 'date_to' => '2020-04-01']);
        $this->as(Role::Disponent)->putJson($this->url($first), $this->body($first, 500, 1060))->assertOk();

        $order = $this->order(['vehicle_id' => $vehicle->id]);
        $res = $this->as(Role::Disponent)->putJson($this->url($order), $this->body($order, 900, 2000, [
            'borders' => [['country' => 'Österreich', 'km_in' => 100, 'km_out' => 200, 'source' => 'tachograph']],
            'extras' => [['title' => 'Wartezeit', 'payment' => 'bar', 'tax_rate' => 19]],
        ]))->assertOk();

        $text = implode(' | ', $res->json('report.warnings'));
        foreach (['geplant waren 560 km', 'nicht zwischen Anfang und Ende', 'letzten Stand dieses Busses', 'Betrag fehlt', 'ausländische Umsatzsteuer'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->as(Role::Disponent)->postJson($this->url($order, '/complete'))->assertOk();
    }

    public function test_liste_noch_zu_erfassen(): void
    {
        $past = $this->order();
        $this->order(['date_from' => '2099-01-01', 'date_to' => '2099-01-01']);
        $this->order(['status' => TripOrderStatus::Entwurf]);
        $this->order(['status' => TripOrderStatus::Erledigt]);
        $this->order(['cancelled_at' => now()]);
        $this->order(['date_from' => null, 'date_to' => null]);

        $this->as(Role::Disponent)->getJson('/api/trip-orders?status=zu_erfassen')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $past->id);
    }
}
