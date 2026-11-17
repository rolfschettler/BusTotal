<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
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

    private function bus(array $override = []): array
    {
        return array_merge([
            'fleet_number' => 'B-01', 'license_plate' => 'M-AB 123', 'type' => 'reisebus',
            'manufacturer' => 'Setra', 'model' => 'S 515 HD', 'seats' => 49,
        ], $override);
    }

    /** Namen (Fuhrparknummern) der Liste in der Reihenfolge der Antwort. */
    private function numbers(string $query = ''): array
    {
        return collect($this->as(Role::Admin)->getJson('/api/vehicles'.$query)->assertOk()->json('data'))
            ->pluck('fleet_number')->all();
    }

    // ---------- Zugriff ----------

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->getJson('/api/vehicles')->assertUnauthorized();
    }

    public function test_fahrer_hat_keinen_zugriff(): void
    {
        $this->as(Role::Fahrer)->getJson('/api/vehicles')->assertForbidden();
        $this->as(Role::Fahrer)->getJson('/api/dashboard/vehicle-deadlines')->assertForbidden();
    }

    public function test_buchhaltung_darf_nur_lesen(): void
    {
        $v = Vehicle::factory()->create();

        $this->as(Role::Buchhaltung)->getJson('/api/vehicles')->assertOk();
        $this->as(Role::Buchhaltung)->getJson("/api/vehicles/{$v->id}")->assertOk();
        $this->as(Role::Buchhaltung)->postJson('/api/vehicles', $this->bus())->assertForbidden();
        $this->as(Role::Buchhaltung)->putJson("/api/vehicles/{$v->id}", $this->bus())->assertForbidden();
        $this->as(Role::Buchhaltung)->deleteJson("/api/vehicles/{$v->id}")->assertForbidden();
    }

    public function test_disponent_darf_anlegen_aendern_archivieren_aber_nicht_wiederherstellen(): void
    {
        $id = $this->as(Role::Disponent)->postJson('/api/vehicles', $this->bus())->assertCreated()->json('id');
        $this->as(Role::Disponent)->putJson("/api/vehicles/{$id}", $this->bus(['seats' => 50]))->assertOk()->assertJsonPath('seats', 50);
        $this->as(Role::Disponent)->deleteJson("/api/vehicles/{$id}")->assertOk();
        $this->as(Role::Disponent)->postJson("/api/vehicles/{$id}/restore")->assertForbidden();
        $this->as(Role::Admin)->postJson("/api/vehicles/{$id}/restore")->assertOk();
    }

    // ---------- Anlegen und Prüfen ----------

    public function test_fahrzeug_wird_mit_uuid_angelegt_und_protokolliert(): void
    {
        $r = $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus([
            'equipment' => ['klima', 'wc'], 'hu_due_date' => '2027-03-31', 'cost_per_km' => 1.85,
        ]))->assertCreated();

        $this->assertTrue(\Illuminate\Support\Str::isUuid($r->json('id')));
        $r->assertJsonPath('equipment', ['klima', 'wc'])->assertJsonPath('hu_due_date', '2027-03-31')
            ->assertJsonPath('display_name', 'B-01 · M-AB 123');
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_id' => $r->json('id')]);
    }

    public function test_pflichtfelder(): void
    {
        $this->as(Role::Admin)->postJson('/api/vehicles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fleet_number', 'license_plate', 'type', 'seats']);
    }

    public function test_kennzeichen_wird_vereinheitlicht_und_ist_eindeutig(): void
    {
        $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus(['license_plate' => ' m-ab   123 ']))
            ->assertCreated()->assertJsonPath('license_plate', 'M-AB 123');

        $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus(['fleet_number' => 'B-02', 'license_plate' => 'm-ab 123']))
            ->assertUnprocessable()->assertJsonValidationErrors('license_plate');
    }

    public function test_fuhrparknummer_ist_eindeutig_aber_beim_eigenen_fahrzeug_erlaubt(): void
    {
        $id = $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus())->json('id');

        $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus(['license_plate' => 'M-XY 9']))
            ->assertUnprocessable()->assertJsonValidationErrors('fleet_number');
        // Speichern ohne die eigene Nummer oder das eigene Kennzeichen zu ändern darf nicht an "gibt es schon" scheitern.
        $this->as(Role::Admin)->putJson("/api/vehicles/{$id}", $this->bus(['seats' => 51]))->assertOk();
    }

    public function test_auch_archivierte_fahrzeuge_blockieren_das_kennzeichen(): void
    {
        $v = Vehicle::factory()->create(['fleet_number' => 'B-09', 'license_plate' => 'M-ZZ 1']);
        $v->delete();

        $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus(['license_plate' => 'M-ZZ 1']))
            ->assertUnprocessable()->assertJsonValidationErrors('license_plate');
    }

    public function test_fahrgestellnummer_ausstattung_und_datum_werden_geprueft(): void
    {
        $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus(['vin' => 'ABC']))->assertJsonValidationErrors('vin');
        $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus(['vin' => 'WKK12345678901234']))->assertCreated()
            ->assertJsonPath('vin', 'WKK12345678901234');
        $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus(['fleet_number' => 'B-2', 'license_plate' => 'M-B 2', 'equipment' => ['jetpack']]))
            ->assertJsonValidationErrors('equipment.0');
        $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus(['fleet_number' => 'B-3', 'license_plate' => 'M-B 3', 'hu_due_date' => '31.03.2027']))
            ->assertJsonValidationErrors('hu_due_date');
        $this->as(Role::Admin)->postJson('/api/vehicles', $this->bus(['fleet_number' => 'B-4', 'license_plate' => 'M-B 4', 'seats' => 0]))
            ->assertJsonValidationErrors('seats');
    }

    // ---------- Fristen ----------

    public function test_fristen_werden_als_abgelaufen_bald_und_ok_bewertet(): void
    {
        $v = Vehicle::factory()->create([
            'hu_due_date' => now()->subDay()->toDateString(),
            'sp_due_date' => now()->addDays(30)->toDateString(),
            'tachograph_due_date' => now()->addDays(31)->toDateString(),
        ])->fresh();

        $status = collect($v->deadlines)->pluck('status', 'kind')->all();
        $this->assertSame(['hu' => 'overdue', 'sp' => 'soon', 'tachograph' => 'ok'], $status);
        $this->assertSame('hu', $v->next_deadline['kind']);
        $this->assertSame(-1, $v->next_deadline['days']);
    }

    public function test_heute_faellige_frist_ist_noch_nicht_abgelaufen(): void
    {
        $v = Vehicle::factory()->create(['hu_due_date' => now()->toDateString()])->fresh();
        $this->assertSame('soon', $v->next_deadline['status']);
        $this->assertSame(0, $v->next_deadline['days']);
    }

    public function test_ohne_fristen_gibt_es_keine_naechste_frist(): void
    {
        $this->assertNull(Vehicle::factory()->create()->fresh()->next_deadline);
    }

    public function test_kachel_zeigt_nur_faellige_fristen_dringendste_zuerst(): void
    {
        Vehicle::factory()->create(['fleet_number' => 'A', 'license_plate' => 'M-A 1', 'hu_due_date' => now()->addDays(10)->toDateString()]);
        Vehicle::factory()->create(['fleet_number' => 'B', 'license_plate' => 'M-B 1', 'hu_due_date' => now()->subDays(5)->toDateString(), 'service_due_date' => now()->addDays(3)->toDateString()]);
        Vehicle::factory()->create(['fleet_number' => 'C', 'license_plate' => 'M-C 1', 'hu_due_date' => now()->addDays(200)->toDateString()]);
        $archived = Vehicle::factory()->create(['fleet_number' => 'D', 'license_plate' => 'M-D 1', 'hu_due_date' => now()->subDays(50)->toDateString()]);
        $archived->delete();

        $r = $this->as(Role::Disponent)->getJson('/api/dashboard/vehicle-deadlines')->assertOk();

        $this->assertSame(3, $r->json('total'));
        $this->assertSame([-5, 3, 10], collect($r->json('items'))->pluck('days')->all());
        $this->assertSame('B · M-B 1', $r->json('items.0.vehicle'));
    }

    public function test_kachel_nicht_fuer_buchhaltung(): void
    {
        $this->as(Role::Buchhaltung)->getJson('/api/dashboard/vehicle-deadlines')->assertForbidden();
    }

    // ---------- Liste ----------

    public function test_suche_findet_nummer_kennzeichen_hersteller_modell_und_fahrgestellnummer(): void
    {
        Vehicle::factory()->create(['fleet_number' => 'B-01', 'license_plate' => 'M-AB 123', 'manufacturer' => 'Setra', 'model' => 'S 515', 'vin' => 'WKK12345678901234']);
        Vehicle::factory()->create(['fleet_number' => 'B-02', 'license_plate' => 'M-CD 456', 'manufacturer' => 'Mercedes', 'model' => 'Tourismo', 'vin' => null]);

        $this->assertSame(['B-02'], $this->numbers('?q=tourismo'));
        $this->assertSame(['B-01'], $this->numbers('?q=ab 12'));
        $this->assertSame(['B-01'], $this->numbers('?q=WKK123'));
        $this->assertSame(['B-01', 'B-02'], $this->numbers('?q=B-0'));
        $this->assertSame([], $this->numbers('?q=%25'), 'Prozentzeichen ist wörtlich gemeint, kein Platzhalter');
    }

    public function test_filter_art_und_fristen_und_archiv(): void
    {
        Vehicle::factory()->create(['fleet_number' => 'B-01', 'license_plate' => 'M-1 1', 'type' => 'reisebus', 'hu_due_date' => now()->subDay()->toDateString()]);
        Vehicle::factory()->create(['fleet_number' => 'B-02', 'license_plate' => 'M-2 2', 'type' => 'minibus', 'sp_due_date' => now()->addDays(10)->toDateString()]);
        Vehicle::factory()->create(['fleet_number' => 'B-03', 'license_plate' => 'M-3 3', 'type' => 'minibus', 'hu_due_date' => now()->addDays(300)->toDateString()]);
        Vehicle::factory()->create(['fleet_number' => 'B-04', 'license_plate' => 'M-4 4'])->delete();

        $this->assertSame(['B-01', 'B-02', 'B-03'], $this->numbers());
        $this->assertSame(['B-02', 'B-03'], $this->numbers('?type=minibus'));
        $this->assertSame(['B-01'], $this->numbers('?deadline=overdue'));
        $this->assertSame(['B-01', 'B-02'], $this->numbers('?deadline=soon'));
        $this->assertSame(['B-04'], $this->numbers('?archived=1'));
    }

    public function test_sortierung_nach_naechster_frist_leere_am_ende(): void
    {
        Vehicle::factory()->create(['fleet_number' => 'B-01', 'license_plate' => 'M-1 1']); // keine Frist
        Vehicle::factory()->create(['fleet_number' => 'B-02', 'license_plate' => 'M-2 2', 'hu_due_date' => '2027-06-01', 'service_due_date' => '2027-02-01']);
        Vehicle::factory()->create(['fleet_number' => 'B-03', 'license_plate' => 'M-3 3', 'sp_due_date' => '2027-04-01']);

        $this->assertSame(['B-02', 'B-03', 'B-01'], $this->numbers('?sort=deadline&dir=asc'));
        $this->assertSame(['B-03', 'B-02', 'B-01'], $this->numbers('?sort=deadline&dir=desc'));
    }

    public function test_sortierung_nach_sitzen_und_kennzeichen(): void
    {
        Vehicle::factory()->create(['fleet_number' => 'B-01', 'license_plate' => 'M-C 1', 'seats' => 30]);
        Vehicle::factory()->create(['fleet_number' => 'B-02', 'license_plate' => 'M-A 1', 'seats' => 50]);
        Vehicle::factory()->create(['fleet_number' => 'B-03', 'license_plate' => 'M-B 1', 'seats' => 50]);

        $this->assertSame(['B-02', 'B-03', 'B-01'], $this->numbers('?sort=seats&dir=desc'));
        $this->assertSame(['B-02', 'B-03', 'B-01'], $this->numbers('?sort=plate'));
    }

    public function test_unbekannte_sortierung_und_sql_einschleusen_werden_abgelehnt(): void
    {
        $this->as(Role::Admin)->getJson('/api/vehicles?sort=seats;DROP TABLE vehicles')->assertUnprocessable();
        $this->as(Role::Admin)->getJson('/api/vehicles?sort=password')->assertUnprocessable();
        $this->assertDatabaseCount('vehicles', 0);
    }

    // ---------- Archiv ----------

    public function test_archivieren_loescht_nicht_und_ist_protokolliert(): void
    {
        $v = Vehicle::factory()->create();
        $this->as(Role::Admin)->deleteJson("/api/vehicles/{$v->id}")->assertOk();

        $this->assertSoftDeleted('vehicles', ['id' => $v->id]);
        $this->assertSame([], $this->numbers());
        $this->as(Role::Admin)->getJson("/api/vehicles/{$v->id}")->assertOk()->assertJsonPath('id', $v->id);
        $this->assertTrue(AuditLog::where('event', 'deleted')->where('auditable_id', $v->id)->exists());
    }

    public function test_optionen_fuer_das_formular(): void
    {
        $r = $this->as(Role::Disponent)->getJson('/api/vehicles/options')->assertOk();
        $this->assertContains('reisebus', collect($r->json('types'))->pluck('value')->all());
        $this->assertContains('klima', collect($r->json('equipment'))->pluck('value')->all());
        $this->assertSame(4, count($r->json('deadlines')));
    }
}
