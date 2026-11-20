<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fristen „Fahrerkarte auslesen“ (28 Tage) und „Fahrzeugeinheit auslesen“ (90 Tage), siehe docs/fachlich/lenk-ruhezeiten.md. */
class ReadDeadlineTest extends TestCase
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

    private function deadline(array $model, string $kind): ?array
    {
        return collect($model['deadlines'])->firstWhere('kind', $kind);
    }

    // ---------- Fahrerkarte ----------

    public function test_fahrerkarte_wird_ausgelesen_naechste_frist_in_28_tagen(): void
    {
        $driver = Driver::factory()->create();

        $res = $this->as(Role::Disponent)->postJson("/api/drivers/{$driver->id}/card-read")->assertOk();

        $this->assertSame(now()->addDays(28)->toDateString(), $res->json('driver_card_read_due_date'));
        $frist = $this->deadline($res->json(), 'driver_card_read');
        $this->assertSame('Fahrerkarte auslesen', $frist['label']);
        $this->assertSame('ok', $frist['status']);
    }

    public function test_ausgelesen_am_frueheren_tag_verkuerzt_die_frist(): void
    {
        $driver = Driver::factory()->create();

        $this->as(Role::Admin)->postJson("/api/drivers/{$driver->id}/card-read", ['date' => now()->subDays(10)->toDateString()])
            ->assertOk()
            ->assertJsonPath('driver_card_read_due_date', now()->addDays(18)->toDateString());
    }

    public function test_ausgelesen_am_in_der_zukunft_wird_abgelehnt(): void
    {
        $driver = Driver::factory()->create();

        $this->as(Role::Admin)->postJson("/api/drivers/{$driver->id}/card-read", ['date' => now()->addDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertNull($driver->fresh()->driver_card_read_due_date);
    }

    public function test_fahrerkarte_vorwarnzeit_ist_sieben_tage(): void
    {
        $bald = Driver::factory()->create(['driver_card_read_due_date' => now()->addDays(7)->toDateString()]);
        $noch = Driver::factory()->create(['driver_card_read_due_date' => now()->addDays(8)->toDateString()]);
        $zuspaet = Driver::factory()->create(['driver_card_read_due_date' => now()->subDay()->toDateString()]);

        $this->assertSame('soon', $this->deadline($bald->fresh()->toArray(), 'driver_card_read')['status']);
        $this->assertSame('ok', $this->deadline($noch->fresh()->toArray(), 'driver_card_read')['status']);
        $this->assertSame('overdue', $this->deadline($zuspaet->fresh()->toArray(), 'driver_card_read')['status']);
    }

    public function test_andere_fristen_behalten_die_vorwarnzeit_von_30_tagen(): void
    {
        $driver = Driver::factory()->create(['code95_due_date' => now()->addDays(25)->toDateString()]);

        $this->assertSame('soon', $this->deadline($driver->fresh()->toArray(), 'code95')['status']);
        $this->assertSame(30, $this->deadline($driver->fresh()->toArray(), 'code95')['warn_days']);
    }

    public function test_fahrerkarte_auslesen_nur_fuer_admin_und_disponent(): void
    {
        $driver = Driver::factory()->create();

        $this->as(Role::Buchhaltung)->postJson("/api/drivers/{$driver->id}/card-read")->assertForbidden();
        $this->as(Role::Fahrer)->postJson("/api/drivers/{$driver->id}/card-read")->assertForbidden();
    }

    public function test_frist_erscheint_in_der_kachel_und_wird_im_formular_speicherbar(): void
    {
        $driver = Driver::factory()->create(['driver_card_read_due_date' => now()->addDays(3)->toDateString()]);

        $this->as(Role::Disponent)->getJson('/api/dashboard/driver-deadlines')->assertOk()
            ->assertJsonPath('items.0.label', 'Fahrerkarte auslesen')
            ->assertJsonPath('items.0.driver_id', $driver->id);

        $body = $driver->only(['personnel_number', 'first_name', 'last_name', 'employment_type']) + [
            'employment_type' => 'vollzeit', 'driver_card_read_due_date' => now()->addDays(20)->toDateString(),
        ];
        $this->as(Role::Disponent)->putJson("/api/drivers/{$driver->id}", $body)->assertOk()
            ->assertJsonPath('driver_card_read_due_date', now()->addDays(20)->toDateString());
    }

    public function test_fristfilter_der_liste_nutzt_die_eigene_vorwarnzeit(): void
    {
        // Fahrerkarte in 20 Tagen auszulesen: nach 7 Tagen Vorwarnzeit noch nicht "bald", obwohl 20 < 30.
        $spaet = Driver::factory()->create(['driver_card_read_due_date' => now()->addDays(20)->toDateString()]);
        $bald = Driver::factory()->create(['driver_card_read_due_date' => now()->addDays(5)->toDateString()]);

        $ids = collect($this->as(Role::Disponent)->getJson('/api/drivers?deadline=soon')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($bald->id));
        $this->assertFalse($ids->contains($spaet->id));
    }

    public function test_fristfilter_der_fahrzeugliste_nutzt_die_eigene_vorwarnzeit(): void
    {
        $spaet = Vehicle::factory()->create(['tachograph_read_due_date' => now()->addDays(25)->toDateString()]);
        $bald = Vehicle::factory()->create(['tachograph_read_due_date' => now()->addDays(10)->toDateString()]);

        $ids = collect($this->as(Role::Disponent)->getJson('/api/vehicles?deadline=soon')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($bald->id));
        $this->assertFalse($ids->contains($spaet->id));
    }

    // ---------- Fahrzeugeinheit ----------

    public function test_fahrzeugeinheit_wird_ausgelesen_naechste_frist_in_90_tagen(): void
    {
        $vehicle = Vehicle::factory()->create();

        $res = $this->as(Role::Disponent)->postJson("/api/vehicles/{$vehicle->id}/tachograph-read")->assertOk();

        $this->assertSame(now()->addDays(90)->toDateString(), $res->json('tachograph_read_due_date'));
        $this->assertSame('Fahrzeugeinheit auslesen', $this->deadline($res->json(), 'tachograph_read')['label']);
    }

    public function test_fahrzeugeinheit_vorwarnzeit_ist_14_tage(): void
    {
        $bald = Vehicle::factory()->create(['tachograph_read_due_date' => now()->addDays(14)->toDateString()]);
        $noch = Vehicle::factory()->create(['tachograph_read_due_date' => now()->addDays(15)->toDateString()]);

        $this->assertSame('soon', $this->deadline($bald->fresh()->toArray(), 'tachograph_read')['status']);
        $this->assertSame('ok', $this->deadline($noch->fresh()->toArray(), 'tachograph_read')['status']);
    }

    public function test_fahrzeugeinheit_auslesen_nur_fuer_admin_und_disponent(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->as(Role::Buchhaltung)->postJson("/api/vehicles/{$vehicle->id}/tachograph-read")->assertForbidden();
        $this->as(Role::Fahrer)->postJson("/api/vehicles/{$vehicle->id}/tachograph-read")->assertForbidden();
    }

    public function test_ausgelesen_wird_im_aenderungsprotokoll_festgehalten(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->as(Role::Admin)->postJson("/api/vehicles/{$vehicle->id}/tachograph-read")->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'updated', 'auditable_id' => $vehicle->id]);
    }
}
