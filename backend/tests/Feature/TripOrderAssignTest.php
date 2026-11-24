<?php

namespace Tests\Feature;

use App\Enums\TripOrderStatus;
use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\TripOrder;
use App\Models\Vehicle;
use App\Services\TripOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Etappe 2, Schritt 3: Fahrzeug und Fahrer zuweisen. Prüfungen sind Warnungen, nie eine Sperre.
 */
class TripOrderAssignTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $over = []): TripOrder
    {
        $order = new TripOrder;
        $order->forceFill(array_merge([
            'status' => TripOrderStatus::Entwurf, 'title' => 'München – Wien', 'trip_kind' => 'gelegenheit',
            'vehicle_type' => 'reisebus', 'bus_no' => 1, 'date_from' => '2027-05-10', 'date_to' => '2027-05-12',
        ], $over))->save();

        return $order;
    }

    private function service(): TripOrderService
    {
        return app(TripOrderService::class);
    }

    private function codes(TripOrder $order): array
    {
        return array_column($this->service()->warnings($order->fresh()), 'code');
    }

    public function test_fahrzeug_und_fahrer_machen_den_auftrag_disponiert(): void
    {
        $order = $this->order();
        $vehicle = Vehicle::factory()->create(['seats' => 49]);
        $driver = Driver::factory()->create();

        $one = $this->service()->assign($order, ['vehicle_id' => $vehicle->id]);
        $this->assertSame(TripOrderStatus::Entwurf, $one->status);   // Fahrer fehlt noch

        $two = $this->service()->assign($order, ['driver_id' => $driver->id]);
        $this->assertSame(TripOrderStatus::Disponiert, $two->status);
        $this->assertSame([], $this->codes($order));

        // Wieder leeren: zurück auf „zu disponieren“
        $this->assertSame(TripOrderStatus::Entwurf, $this->service()->assign($order, ['driver_id' => null])->status);
    }

    public function test_fremdunternehmer_genuegt_und_schliesst_eigenes_fahrzeug_aus(): void
    {
        $order = $this->order();

        $res = $this->service()->assign($order, ['external_company' => 'Bus Huber GmbH', 'external_plate' => 'M-HU 123']);
        $this->assertSame(TripOrderStatus::Disponiert, $res->status);

        $this->expectException(ValidationException::class);
        $this->service()->assign($order, ['vehicle_id' => Vehicle::factory()->create()->id]);
    }

    public function test_unmoegliche_angaben_werden_abgelehnt(): void
    {
        $order = $this->order();
        $driver = Driver::factory()->create();

        foreach ([
            ['driver2_id' => $driver->id],                                          // Fahrer 2 ohne Fahrer 1
            ['driver_id' => $driver->id, 'driver2_id' => $driver->id],             // dieselbe Person
            ['date_from' => '2027-05-12', 'date_to' => '2027-05-10'],              // Ende vor Beginn
            ['vehicle_id' => '00000000-0000-0000-0000-000000000000'],              // unbekanntes Fahrzeug
        ] as $data) {
            try {
                $this->service()->assign($order, $data);
                $this->fail('Erwartet: Ablehnung für '.json_encode($data));
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_doppelbelegung_von_fahrzeug_und_fahrer_warnt(): void
    {
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $other = $this->order(['title' => 'Salzburg', 'date_from' => '2027-05-12', 'date_to' => '2027-05-14', 'vehicle_id' => $vehicle->id, 'driver2_id' => $driver->id]);
        $order = $this->order();

        $this->service()->assign($order, ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id]);

        $codes = $this->codes($order);
        $this->assertContains('vehicle_busy', $codes);
        $this->assertContains('driver_busy', $codes);   // auch als Fahrer 2 des anderen Auftrags
        $text = $this->service()->warnings($order->fresh())[0]['text'];
        $this->assertStringContainsString('Salzburg am 12.05.2027 bis 14.05.2027', $text);

        // Ohne Überschneidung keine Warnung
        $this->service()->assign($order, ['date_from' => '2027-05-20', 'date_to' => '2027-05-21']);
        $this->assertSame([], $this->codes($order));
        $this->assertNotNull($other->fresh());
    }

    public function test_abgelaufene_fristen_abwesenheit_fuehrerschein_und_sitzplaetze_warnen(): void
    {
        $vehicle = Vehicle::factory()->create(['type' => 'minibus', 'seats' => 40, 'hu_due_date' => '2027-05-11']);
        $driver = Driver::factory()->create(['license_classes' => ['B'], 'code95_due_date' => '2020-01-01']);
        $driver->absences()->create(['type' => 'urlaub', 'start_date' => '2027-05-01', 'end_date' => '2027-05-10']);
        $order = $this->order(['passengers' => 'ca. 50 Pers.']);

        $this->service()->assign($order, ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id]);

        $this->assertEqualsCanonicalizing(
            ['vehicle_type', 'seats', 'vehicle_deadline', 'driver_deadline', 'license', 'absence'],
            $this->codes($order),
        );
        $texts = implode(' | ', array_column($this->service()->warnings($order->fresh()), 'text'));
        $this->assertStringContainsString('läuft am 11.05.2027 ab', $texts);   // läuft während der Fahrt ab
        $this->assertStringContainsString('seit 01.01.2020 abgelaufen', $texts);
        $this->assertStringContainsString('Urlaub 01.05.2027 bis 10.05.2027', $texts);
    }

    public function test_kleinbus_darf_mit_d1_gefahren_werden_grosser_bus_nicht(): void
    {
        $driver = Driver::factory()->create(['license_classes' => ['D1']]);
        $small = Vehicle::factory()->create(['type' => 'reisebus', 'seats' => 16]);
        $big = Vehicle::factory()->create(['seats' => 49]);

        $order = $this->order();
        $this->service()->assign($order, ['vehicle_id' => $small->id, 'driver_id' => $driver->id]);
        $this->assertNotContains('license', $this->codes($order));

        $this->service()->assign($order, ['vehicle_id' => $big->id]);
        $this->assertContains('license', $this->codes($order));
    }

    public function test_nach_dem_ausgeben_nur_mit_begruendung_und_im_protokoll(): void
    {
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $order = $this->order(['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id]);
        $order->forceFill(['status' => TripOrderStatus::Ausgegeben, 'number' => 'FA-2027-00001'])->save();
        $newDriver = Driver::factory()->create();

        try {
            $this->service()->assign($order, ['driver_id' => $newDriver->id]);
            $this->fail('Ohne Begründung darf nichts geändert werden.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Begründung', $e->errors()['assignment'][0]);
        }
        $this->assertSame($driver->id, $order->fresh()->driver_id);

        $res = $this->service()->assign($order, ['driver_id' => $newDriver->id], 'Fahrer krank');
        $this->assertSame($newDriver->id, $res->driver_id);
        $this->assertSame(TripOrderStatus::Ausgegeben, $res->status);   // bleibt ausgegeben
        $this->assertTrue(AuditLog::where('event', 'reassigned')->where('auditable_id', $order->id)->exists());

        // Hinweistexte für den Fahrer brauchen keine Begründung
        $this->service()->assign($order, ['driver_note' => 'Treffpunkt 6 Uhr']);
        $this->assertSame('Treffpunkt 6 Uhr', $order->fresh()->driver_note);
    }

    public function test_erledigte_und_abgerechnete_auftraege_sind_gesperrt(): void
    {
        $order = $this->order();
        $order->forceFill(['status' => TripOrderStatus::Erledigt])->save();

        $this->expectException(ValidationException::class);
        $this->service()->assign($order, ['passengers' => 40]);
    }
}
