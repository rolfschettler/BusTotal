<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DriverTest extends TestCase
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

    private function person(array $override = []): array
    {
        return array_merge([
            'personnel_number' => 'P-001', 'first_name' => 'Hans', 'last_name' => 'Meier', 'employment_type' => 'vollzeit',
        ], $override);
    }

    /** Personalnummern der Liste in der Reihenfolge der Antwort. */
    private function numbers(string $query = ''): array
    {
        return collect($this->as(Role::Admin)->getJson('/api/drivers'.$query)->assertOk()->json('data'))
            ->pluck('personnel_number')->all();
    }

    // ---------- Zugriff: Personendaten nur für Admin und Disponent ----------

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->getJson('/api/drivers')->assertUnauthorized();
    }

    public function test_buchhaltung_und_fahrer_haben_keinen_zugriff(): void
    {
        $d = Driver::factory()->create();
        foreach ([Role::Buchhaltung, Role::Fahrer] as $role) {
            $this->as($role)->getJson('/api/drivers')->assertForbidden();
            $this->as($role)->getJson("/api/drivers/{$d->id}")->assertForbidden();
            $this->as($role)->postJson('/api/drivers', $this->person())->assertForbidden();
            $this->as($role)->postJson("/api/drivers/{$d->id}/absences", [])->assertForbidden();
            $this->as($role)->getJson('/api/dashboard/driver-deadlines')->assertForbidden();
        }
    }

    public function test_disponent_darf_alles_ausser_wiederherstellen(): void
    {
        $id = $this->as(Role::Disponent)->postJson('/api/drivers', $this->person())->assertCreated()->json('id');
        $this->as(Role::Disponent)->putJson("/api/drivers/{$id}", $this->person(['last_name' => 'Müller']))->assertOk()->assertJsonPath('last_name', 'Müller');
        $this->as(Role::Disponent)->deleteJson("/api/drivers/{$id}")->assertOk();
        $this->as(Role::Disponent)->postJson("/api/drivers/{$id}/restore")->assertForbidden();
        $this->as(Role::Admin)->postJson("/api/drivers/{$id}/restore")->assertOk()->assertJsonPath('deleted_at', null);
    }

    // ---------- Anlegen und Prüfen ----------

    public function test_fahrer_wird_mit_uuid_angelegt_und_protokolliert(): void
    {
        $r = $this->as(Role::Admin)->postJson('/api/drivers', $this->person([
            'license_classes' => ['D', 'DE'], 'code95_due_date' => '2028-09-14', 'hourly_rate' => 24.5,
        ]))->assertCreated();

        $this->assertTrue(Str::isUuid($r->json('id')));
        $r->assertJsonPath('display_name', 'Meier, Hans')->assertJsonPath('license_classes', ['D', 'DE'])
            ->assertJsonPath('code95_due_date', '2028-09-14')->assertJsonPath('absences', [])->assertJsonPath('absent_today', false);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_id' => $r->json('id')]);
    }

    public function test_pflichtfelder_und_eindeutige_personalnummer(): void
    {
        $this->as(Role::Admin)->postJson('/api/drivers', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['personnel_number', 'first_name', 'last_name', 'employment_type']);

        $id = $this->as(Role::Admin)->postJson('/api/drivers', $this->person())->json('id');
        $this->as(Role::Admin)->postJson('/api/drivers', $this->person(['last_name' => 'Anders']))
            ->assertUnprocessable()->assertJsonValidationErrors('personnel_number');
        // Die eigene Nummer beim Speichern ist erlaubt.
        $this->as(Role::Admin)->putJson("/api/drivers/{$id}", $this->person(['first_name' => 'Karl']))->assertOk();
    }

    public function test_klassen_daten_und_geburtsdatum_werden_geprueft(): void
    {
        $this->as(Role::Admin)->postJson('/api/drivers', $this->person(['license_classes' => ['Z']]))->assertJsonValidationErrors('license_classes.0');
        $this->as(Role::Admin)->postJson('/api/drivers', $this->person(['license_classes' => ['D', 'D']]))->assertJsonValidationErrors('license_classes.0');
        $this->as(Role::Admin)->postJson('/api/drivers', $this->person(['code95_due_date' => '14.09.2028']))->assertJsonValidationErrors('code95_due_date');
        $this->as(Role::Admin)->postJson('/api/drivers', $this->person(['birth_date' => now()->addDay()->toDateString()]))->assertJsonValidationErrors('birth_date');
        $this->as(Role::Admin)->postJson('/api/drivers', $this->person(['email' => 'keine-mail']))->assertJsonValidationErrors('email');
    }

    // ---------- Fristen ----------

    public function test_fristen_werden_bewertet_und_die_naechste_gefunden(): void
    {
        $d = Driver::factory()->create([
            'license_due_date' => now()->addYears(5)->toDateString(),
            'driver_card_due_date' => now()->addDays(12)->toDateString(),
            'code95_due_date' => now()->subDays(2)->toDateString(),
        ])->fresh();

        $this->assertSame(['license' => 'ok', 'driver_card' => 'soon', 'code95' => 'overdue'], collect($d->deadlines)->pluck('status', 'kind')->all());
        $this->assertSame('code95', $d->next_deadline['kind']);
        $this->assertSame(-2, $d->next_deadline['days']);
    }

    public function test_kachel_zeigt_nur_faellige_fristen_und_keine_archivierten(): void
    {
        Driver::factory()->create(['last_name' => 'A', 'first_name' => 'A', 'code95_due_date' => now()->addDays(10)->toDateString()]);
        Driver::factory()->create(['last_name' => 'B', 'first_name' => 'B', 'pbs_due_date' => now()->subDays(5)->toDateString(), 'medical_due_date' => now()->addDays(3)->toDateString()]);
        Driver::factory()->create(['last_name' => 'C', 'first_name' => 'C', 'code95_due_date' => now()->addDays(300)->toDateString()]);
        Driver::factory()->create(['last_name' => 'D', 'first_name' => 'D', 'code95_due_date' => now()->subDays(99)->toDateString()])->delete();

        $r = $this->as(Role::Disponent)->getJson('/api/dashboard/driver-deadlines')->assertOk();

        $this->assertSame(3, $r->json('total'));
        $this->assertSame([-5, 3, 10], collect($r->json('items'))->pluck('days')->all());
        $this->assertSame('B, B', $r->json('items.0.driver'));
        $this->assertArrayHasKey('driver_id', $r->json('items.0'));
    }

    // ---------- Liste ----------

    public function test_suche_findet_name_nummer_ort_und_kartennummer(): void
    {
        Driver::factory()->create(['personnel_number' => 'P-001', 'first_name' => 'Hans', 'last_name' => 'Müller', 'city' => 'Bonn', 'driver_card_number' => 'DE12345']);
        Driver::factory()->create(['personnel_number' => 'P-002', 'first_name' => 'Eva', 'last_name' => 'Schmidt', 'city' => 'Aachen', 'license_number' => 'B0815']);

        $this->assertSame(['P-001'], $this->numbers('?q=muller'));
        $this->assertSame(['P-002'], $this->numbers('?q=aachen'));
        $this->assertSame(['P-001'], $this->numbers('?q=DE123'));
        $this->assertSame(['P-002'], $this->numbers('?q=b08'));
        $this->assertSame(['P-001', 'P-002'], $this->numbers('?q=P-00'));
        $this->assertSame([], $this->numbers('?q=%25'));
    }

    public function test_filter_beschaeftigung_fristen_und_archiv(): void
    {
        Driver::factory()->create(['personnel_number' => 'P-001', 'last_name' => 'A', 'employment_type' => 'vollzeit', 'license_due_date' => now()->subDay()->toDateString()]);
        Driver::factory()->create(['personnel_number' => 'P-002', 'last_name' => 'B', 'employment_type' => 'aushilfe', 'medical_due_date' => now()->addDays(10)->toDateString()]);
        Driver::factory()->create(['personnel_number' => 'P-003', 'last_name' => 'C', 'employment_type' => 'aushilfe']);
        Driver::factory()->create(['personnel_number' => 'P-004', 'last_name' => 'D'])->delete();

        $this->assertSame(['P-001', 'P-002', 'P-003'], $this->numbers());
        $this->assertSame(['P-002', 'P-003'], $this->numbers('?employment_type=aushilfe'));
        $this->assertSame(['P-001'], $this->numbers('?deadline=overdue'));
        $this->assertSame(['P-001', 'P-002'], $this->numbers('?deadline=soon'));
        $this->assertSame(['P-004'], $this->numbers('?archived=1'));
    }

    public function test_sortierung_nach_name_und_naechster_frist_leere_am_ende(): void
    {
        Driver::factory()->create(['personnel_number' => 'P-001', 'first_name' => 'Zoe', 'last_name' => 'Adler']);
        Driver::factory()->create(['personnel_number' => 'P-002', 'first_name' => 'Anna', 'last_name' => 'Adler', 'pbs_due_date' => '2027-06-01', 'medical_due_date' => '2027-02-01']);
        Driver::factory()->create(['personnel_number' => 'P-003', 'first_name' => 'Bert', 'last_name' => 'Bauer', 'code95_due_date' => '2027-04-01']);

        $this->assertSame(['P-002', 'P-001', 'P-003'], $this->numbers(), 'Nachname, bei Gleichstand Vorname');
        $this->assertSame(['P-003', 'P-001', 'P-002'], $this->numbers('?sort=name&dir=desc'));
        $this->assertSame(['P-002', 'P-003', 'P-001'], $this->numbers('?sort=deadline'));
        $this->assertSame(['P-003', 'P-002', 'P-001'], $this->numbers('?sort=deadline&dir=desc'));
    }

    public function test_sql_einschleusen_wird_abgelehnt(): void
    {
        $this->as(Role::Admin)->getJson('/api/drivers?sort=name;DROP TABLE drivers')->assertUnprocessable();
        $this->as(Role::Admin)->getJson('/api/drivers?sort=hourly_rate')->assertUnprocessable();
        Driver::factory()->create();
        $this->assertDatabaseCount('drivers', 1);
    }

    // ---------- Archiv ----------

    public function test_archivieren_loescht_nicht(): void
    {
        $d = Driver::factory()->create();
        $this->as(Role::Admin)->deleteJson("/api/drivers/{$d->id}")->assertOk();

        $this->assertSoftDeleted('drivers', ['id' => $d->id]);
        $this->assertSame([], $this->numbers());
        $this->as(Role::Admin)->getJson("/api/drivers/{$d->id}")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_id' => $d->id]);
    }

    // ---------- Abwesenheiten ----------

    private function absence(array $override = []): array
    {
        return array_merge(['type' => 'urlaub', 'start_date' => '2027-08-02', 'end_date' => '2027-08-13', 'notes' => 'Sommer'], $override);
    }

    public function test_abwesenheit_anlegen_aendern_loeschen(): void
    {
        $d = Driver::factory()->create();
        $id = $this->as(Role::Disponent)->postJson("/api/drivers/{$d->id}/absences", $this->absence())->assertCreated()
            ->assertJsonPath('start_date', '2027-08-02')->json('id');

        $this->as(Role::Disponent)->putJson("/api/drivers/{$d->id}/absences/{$id}", $this->absence(['type' => 'krank', 'end_date' => '2027-08-20']))
            ->assertOk()->assertJsonPath('type', 'krank')->assertJsonPath('end_date', '2027-08-20');
        $this->as(Role::Disponent)->getJson("/api/drivers/{$d->id}")->assertJsonCount(1, 'absences');

        $this->as(Role::Disponent)->deleteJson("/api/drivers/{$d->id}/absences/{$id}")->assertOk();
        $this->assertDatabaseMissing('driver_absences', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_id' => $id]);
    }

    public function test_abwesenheit_pruefungen(): void
    {
        $d = Driver::factory()->create();
        $url = "/api/drivers/{$d->id}/absences";

        $this->as(Role::Admin)->postJson($url, [])->assertJsonValidationErrors(['type', 'start_date', 'end_date']);
        $this->as(Role::Admin)->postJson($url, $this->absence(['type' => 'party']))->assertJsonValidationErrors('type');
        $this->as(Role::Admin)->postJson($url, $this->absence(['start_date' => '2027-08-10', 'end_date' => '2027-08-09']))->assertJsonValidationErrors('end_date');
        // Ein einzelner Tag ist erlaubt (Beginn = Ende).
        $this->as(Role::Admin)->postJson($url, $this->absence(['start_date' => '2027-09-01', 'end_date' => '2027-09-01']))->assertCreated();
    }

    public function test_abwesenheiten_duerfen_sich_nicht_ueberschneiden(): void
    {
        $d = Driver::factory()->create();
        $other = Driver::factory()->create();
        $url = "/api/drivers/{$d->id}/absences";
        $first = $this->as(Role::Admin)->postJson($url, $this->absence())->json('id'); // 02.–13.08.

        // Überschneidung am Rand (Enddatum zählt mit), mittendrin und umschließend: abgelehnt.
        foreach ([['2027-08-13', '2027-08-14'], ['2027-08-05', '2027-08-06'], ['2027-07-30', '2027-08-30']] as [$from, $to]) {
            $this->as(Role::Admin)->postJson($url, $this->absence(['start_date' => $from, 'end_date' => $to]))
                ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        }
        // Direkt anschließend ist erlaubt, ebenso derselbe Zeitraum bei einem anderen Fahrer.
        $this->as(Role::Admin)->postJson($url, $this->absence(['start_date' => '2027-08-14', 'end_date' => '2027-08-15']))->assertCreated();
        $this->as(Role::Admin)->postJson("/api/drivers/{$other->id}/absences", $this->absence())->assertCreated();
        // Die eigene Abwesenheit ändern kollidiert nicht mit sich selbst.
        $this->as(Role::Admin)->putJson("{$url}/{$first}", $this->absence(['end_date' => '2027-08-12']))->assertOk();
    }

    public function test_abwesenheit_eines_anderen_fahrers_ist_nicht_erreichbar(): void
    {
        $a = Driver::factory()->create();
        $b = Driver::factory()->create();
        $id = $this->as(Role::Admin)->postJson("/api/drivers/{$a->id}/absences", $this->absence())->json('id');

        $this->as(Role::Admin)->putJson("/api/drivers/{$b->id}/absences/{$id}", $this->absence())->assertNotFound();
        $this->as(Role::Admin)->deleteJson("/api/drivers/{$b->id}/absences/{$id}")->assertNotFound();
        $this->assertDatabaseHas('driver_absences', ['id' => $id]);
    }

    public function test_heute_abwesend_wird_in_liste_und_detail_gemeldet(): void
    {
        $away = Driver::factory()->create(['personnel_number' => 'P-001', 'last_name' => 'A']);
        $here = Driver::factory()->create(['personnel_number' => 'P-002', 'last_name' => 'B']);
        $future = Driver::factory()->create(['personnel_number' => 'P-003', 'last_name' => 'C']);
        $away->absences()->create(['type' => 'krank', 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->toDateString()]);
        $future->absences()->create(['type' => 'urlaub', 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(5)->toDateString()]);

        $list = $this->as(Role::Admin)->getJson('/api/drivers')->json('data');
        $this->assertSame([true, false, false], collect($list)->pluck('absent_today')->all());
        $this->as(Role::Admin)->getJson("/api/drivers/{$away->id}")->assertJsonPath('absent_today', true);
        $this->as(Role::Admin)->getJson("/api/drivers/{$here->id}")->assertJsonPath('absent_today', false);
    }

    public function test_abwesenheiten_verschwinden_nur_mit_dem_fahrer_nicht_beim_archivieren(): void
    {
        $d = Driver::factory()->create();
        $d->absences()->create(['type' => 'urlaub', 'start_date' => '2027-08-02', 'end_date' => '2027-08-03']);
        $d->delete();

        $this->assertDatabaseCount('driver_absences', 1);
        $this->as(Role::Admin)->getJson("/api/drivers/{$d->id}")->assertJsonCount(1, 'absences');
    }

    public function test_optionen_fuer_das_formular(): void
    {
        $r = $this->as(Role::Disponent)->getJson('/api/drivers/options')->assertOk();
        $this->assertContains('DE', collect($r->json('license_classes'))->pluck('value')->all());
        $this->assertContains('urlaub', collect($r->json('absence_types'))->pluck('value')->all());
        $this->assertContains('vollzeit', collect($r->json('employment_types'))->pluck('value')->all());
        $this->assertSame(5, count($r->json('deadlines')));
    }
}
