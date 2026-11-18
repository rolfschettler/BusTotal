<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Requests\DriverRequest;
use App\Http\Requests\VehicleRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverAbsence;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DemoFleetGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;
use Tests\TestCase;

/** Demo-Fuhrpark: Fahrzeuge, Fahrer und Abwesenheiten (siehe DemoFleetService). */
class DemoFleetTest extends TestCase
{
    use RefreshDatabase;

    private const TECHNICAL = ['id', 'is_demo', 'created_at', 'updated_at', 'deleted_at', 'display_name', 'deadlines', 'next_deadline'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    private function demo(array $extra = []): void
    {
        $this->artisan('bustotal:demo-daten', ['--art' => 'fuhrpark'] + $extra)->assertSuccessful();
    }

    /** Die Eingaberegeln der Anwendung ohne die Eindeutigkeitsprüfung (die sichert schon die Datenbank). */
    private function rulesWithoutUnique(array $rules): array
    {
        return collect($rules)->map(fn ($r) => array_values(array_filter((array) $r, fn ($x) => ! $x instanceof Unique)))->all();
    }

    public function test_standardmenge_wird_angelegt_und_ist_markiert(): void
    {
        $this->demo();

        $this->assertSame(14, Vehicle::withTrashed()->where('is_demo', true)->count());
        $this->assertSame(20, Driver::withTrashed()->where('is_demo', true)->count());
        $this->assertGreaterThan(5, DriverAbsence::count());
    }

    public function test_alle_fahrzeuge_und_fahrer_bestehen_die_pruefregeln(): void
    {
        $this->demo(['--fahrzeuge' => 60, '--fahrer' => 100]);
        $fehler = [];

        $vehicleRules = $this->rulesWithoutUnique((new VehicleRequest)->rules());
        foreach (Vehicle::withTrashed()->get() as $v) {
            $val = Validator::make(Arr::except($v->attributesToArray(), self::TECHNICAL), $vehicleRules);
            if ($val->fails()) {
                $fehler[] = $v->fleet_number.': '.implode(' ', $val->errors()->all());
            }
        }

        $driverRules = $this->rulesWithoutUnique((new DriverRequest)->rules());
        foreach (Driver::withTrashed()->get() as $d) {
            $val = Validator::make(Arr::except($d->attributesToArray(), self::TECHNICAL), $driverRules);
            if ($val->fails()) {
                $fehler[] = $d->personnel_number.': '.implode(' ', $val->errors()->all());
            }
        }

        $this->assertSame([], $fehler, implode("\n", $fehler));
    }

    public function test_abwesenheiten_ueberschneiden_sich_nie(): void
    {
        $this->demo(['--fahrer' => 200]);

        foreach (Driver::withTrashed()->with('absences')->get() as $d) {
            $liste = $d->absences->sortBy('start_date')->values();
            foreach ($liste as $k => $a) {
                $this->assertGreaterThanOrEqual($a->start_date, $a->end_date);
                if ($k > 0) {
                    $this->assertGreaterThan($liste[$k - 1]->end_date, $a->start_date, $d->personnel_number.' hat überschneidende Abwesenheiten.');
                }
            }
        }
    }

    public function test_es_gibt_immer_faellige_fristen_und_abwesende_zum_vorfuehren(): void
    {
        $this->demo();

        $heute = now()->toDateString();
        $in30 = now()->addDays(Vehicle::WARN_DAYS)->toDateString();

        $this->assertGreaterThan(0, Vehicle::where('hu_due_date', '<', $heute)->count(), 'abgelaufene HU');
        $this->assertGreaterThan(0, Vehicle::where('sp_due_date', '>=', $heute)->where('sp_due_date', '<=', $in30)->count(), 'bald fällige SP');
        $this->assertGreaterThan(0, Driver::where('driver_card_due_date', '<', $heute)->count(), 'abgelaufene Fahrerkarte');
        $this->assertGreaterThan(0, Driver::withAbsentToday()->get()->where('absent_today', true)->count(), 'heute abwesend');
    }

    public function test_nur_gezielt_ausgewaehlte_fahrzeuge_tragen_eine_warnung(): void
    {
        $this->demo(['--fahrzeuge' => 100]);

        $grenze = now()->addDays(Vehicle::WARN_DAYS)->toDateString();
        $auffaellig = Vehicle::withTrashed()->where(function ($q) use ($grenze) {
            foreach (array_keys(Vehicle::DEADLINES) as $col) {
                $q->orWhere($col, '<=', $grenze);
            }
        })->count();

        // 4 von je 10 Fahrzeugen sind gezielt auffällig; der Zufall darf keine weiteren hinzufügen.
        $this->assertSame(40, $auffaellig);
    }

    public function test_zweiter_lauf_legt_nichts_doppelt_an_und_groessere_menge_behaelt_die_ersten(): void
    {
        $this->demo();
        $erste = Vehicle::withTrashed()->pluck('license_plate', 'fleet_number')->all();
        $abwesenheiten = DriverAbsence::count();

        $this->demo();
        $this->assertSame(14, Vehicle::withTrashed()->count());
        $this->assertSame(20, Driver::withTrashed()->count());
        $this->assertSame($abwesenheiten, DriverAbsence::count());

        $this->demo(['--fahrzeuge' => 20, '--fahrer' => 30]);
        $this->assertSame(20, Vehicle::withTrashed()->count());
        $this->assertSame(30, Driver::withTrashed()->count());
        foreach ($erste as $nummer => $kennzeichen) {
            $this->assertSame($kennzeichen, Vehicle::withTrashed()->where('fleet_number', $nummer)->value('license_plate'));
        }
    }

    public function test_generator_liefert_am_selben_tag_dieselben_daten(): void
    {
        $tag = CarbonImmutable::parse('2026-11-22');

        $this->assertEquals((new DemoFleetGenerator($tag))->vehicle(5), (new DemoFleetGenerator($tag))->vehicle(5));
        $this->assertEquals((new DemoFleetGenerator($tag))->driver(7), (new DemoFleetGenerator($tag))->driver(7));
    }

    public function test_kennzeichen_und_nummern_sind_eindeutig(): void
    {
        $this->demo(['--fahrzeuge' => 200, '--fahrer' => 500]);

        $this->assertSame(200, Vehicle::withTrashed()->count());
        $this->assertSame(500, Driver::withTrashed()->count());
    }

    public function test_einige_sind_archiviert(): void
    {
        $this->demo(['--fahrzeuge' => 24, '--fahrer' => 30]);

        $this->assertSame(2, Vehicle::onlyTrashed()->count());
        $this->assertSame(2, Driver::onlyTrashed()->count());
    }

    public function test_nur_ein_protokolleintrag_je_lauf(): void
    {
        $vorher = AuditLog::count();

        $this->demo();

        $this->assertSame($vorher + 1, AuditLog::count());
        $this->assertSame(1, AuditLog::where('event', 'demo_fleet_created')->count());
    }

    public function test_echte_datensaetze_mit_gleicher_nummer_werden_nicht_ueberschrieben(): void
    {
        Vehicle::factory()->create(['fleet_number' => 'B-01', 'license_plate' => 'M-XY 999']);
        Driver::factory()->create(['personnel_number' => 'P-001']);

        $this->artisan('bustotal:demo-daten', ['--art' => 'fuhrpark'])->expectsOutputToContain('2 übersprungen')->assertSuccessful();

        $this->assertSame(14, Vehicle::withTrashed()->count());
        $this->assertSame(20, Driver::withTrashed()->count());
        $this->assertFalse((bool) Vehicle::where('fleet_number', 'B-01')->value('is_demo'));
    }

    public function test_entfernen_trifft_nur_demo_datensaetze(): void
    {
        $echtesFahrzeug = Vehicle::factory()->create(['fleet_number' => 'ECHT-1', 'license_plate' => 'M-EC 111']);
        $echterFahrer = Driver::factory()->create(['personnel_number' => 'ECHT-P1']);
        $this->demo();

        $this->artisan('bustotal:demo-daten', ['--art' => 'fuhrpark', '--entfernen' => true])->assertSuccessful();

        $this->assertSame([$echtesFahrzeug->id], Vehicle::withTrashed()->pluck('id')->all());
        $this->assertSame([$echterFahrer->id], Driver::withTrashed()->pluck('id')->all());
        $this->assertSame(0, DriverAbsence::count());
        $this->assertSame(1, AuditLog::where('event', 'demo_fleet_removed')->count());
    }

    public function test_standardlauf_legt_kunden_und_fuhrpark_an_und_entfernt_beides(): void
    {
        $this->artisan('bustotal:demo-daten', ['--anzahl' => 5])->assertSuccessful();

        $this->assertSame(5, Customer::withTrashed()->count());
        $this->assertSame(14, Vehicle::withTrashed()->count());
        $this->assertSame(20, Driver::withTrashed()->count());

        $this->artisan('bustotal:demo-daten', ['--entfernen' => true])->assertSuccessful();

        $this->assertSame(0, Customer::withTrashed()->count());
        $this->assertSame(0, Vehicle::withTrashed()->count());
        $this->assertSame(0, Driver::withTrashed()->count());
    }

    public function test_art_kunden_laesst_den_fuhrpark_in_ruhe(): void
    {
        $this->artisan('bustotal:demo-daten', ['--art' => 'kunden', '--anzahl' => 3])->assertSuccessful();

        $this->assertSame(0, Vehicle::withTrashed()->count());
        $this->assertSame(0, Driver::withTrashed()->count());
    }

    public function test_eingaben_werden_geprueft(): void
    {
        $this->artisan('bustotal:demo-daten', ['--art' => 'fuhrpark', '--fahrzeuge' => 999])->assertFailed();
        $this->artisan('bustotal:demo-daten', ['--art' => 'quatsch'])->assertFailed();
        $this->assertSame(0, Vehicle::withTrashed()->count());
    }

    public function test_in_produktiver_umgebung_nur_mit_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('bustotal:demo-daten', ['--art' => 'fuhrpark'])->assertFailed();
        $this->assertSame(0, Vehicle::withTrashed()->count());

        $this->artisan('bustotal:demo-daten', ['--art' => 'fuhrpark', '--force' => true])->assertSuccessful();
        $this->assertSame(14, Vehicle::withTrashed()->count());
    }

    public function test_formular_kann_die_demo_markierung_nicht_setzen(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $id = $this->actingAs($admin, 'sanctum')->postJson('/api/vehicles', [
            'fleet_number' => 'B-77', 'license_plate' => 'M-AB 777', 'type' => 'reisebus', 'seats' => 40, 'is_demo' => true,
        ])->assertCreated()->json('id');

        $this->assertFalse((bool) Vehicle::find($id)->is_demo);
    }
}
