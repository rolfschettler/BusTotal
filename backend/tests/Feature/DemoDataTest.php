<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Requests\CustomerRequest;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\NumberRange;
use App\Models\User;
use App\Services\CustomerService;
use App\Support\DemoDataGenerator;
use App\Support\ImportId;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    private function demo(int $count = 200): void
    {
        $this->artisan('bustotal:demo-daten', ['--anzahl' => $count])->assertSuccessful();
    }

    private function all(): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::withTrashed()->where('is_demo', true)->get();
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create(['role' => Role::Admin]), 'sanctum');
    }

    // ---------- Nummern ----------

    public function test_200_kunden_mit_luecken_losen_nummern(): void
    {
        $this->demo(200);

        $this->assertSame(200, Customer::withTrashed()->count());
        $this->assertSame(200, $this->all()->count(), 'Alle müssen als Demo markiert sein.');

        $erwartet = array_map(fn ($i) => 'K-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), range(1, 200));
        $tatsaechlich = Customer::withTrashed()->pluck('customer_number')->sort()->values()->all();
        $this->assertSame($erwartet, $tatsaechlich, 'Kundennummern müssen fortlaufend und ohne Lücke sein.');

        $debitoren = Customer::withTrashed()->pluck('debitor_number')->map(fn ($n) => (int) $n)->sort()->values()->all();
        $this->assertSame(range(10000, 10199), $debitoren);

        $this->assertSame(201, (int) NumberRange::where('type', 'kunde')->value('next_number'));
        $this->assertSame(10200, (int) NumberRange::where('type', 'debitor')->value('next_number'));
    }

    public function test_aeltere_kunden_haben_kleinere_nummern(): void
    {
        $this->demo(60);

        $nummern = Customer::withTrashed()->orderBy('created_at')->orderBy('customer_number')->pluck('customer_number')->all();
        $sortiert = $nummern;
        sort($sortiert);

        $this->assertSame($sortiert, $nummern);
    }

    // ---------- Korrektheit ----------

    public function test_alle_kunden_bestehen_die_pruefregeln_der_anwendung(): void
    {
        $this->demo(200);

        $felder = ['type', 'company_name', 'salutation', 'first_name', 'last_name', 'email', 'phone', 'mobile', 'website',
            'vat_id', 'street', 'postal_code', 'city', 'country', 'payment_term_days', 'notes'];
        $fehler = [];

        foreach ($this->all() as $c) {
            $daten = Arr::only($c->toArray(), $felder);
            $request = CustomerRequest::create('/api/customers', 'POST', $daten);
            $v = Validator::make($daten, $request->rules(), $request->messages());

            if ($v->fails()) {
                $fehler[] = $c->customer_number.': '.implode(' ', $v->errors()->all());
            }
        }

        $this->assertSame([], $fehler, "Diese Demo-Kunden würden die Anwendung selbst ablehnen:\n".implode("\n", $fehler));
    }

    public function test_ansprechpartner_und_adressen_sind_gueltig(): void
    {
        $this->demo(200);

        foreach (Contact::all() as $c) {
            $v = Validator::make($c->only(['salutation', 'first_name', 'last_name', 'position', 'email', 'phone', 'mobile']), [
                'salutation' => ['nullable', 'string', 'max:20'], 'first_name' => ['nullable', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'], 'position' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'mobile' => ['nullable', 'string', 'max:50'],
            ]);
            $this->assertFalse($v->fails(), 'Ansprechpartner '.$c->last_name.': '.implode(' ', $v->errors()->all()));
        }

        foreach (Address::all() as $a) {
            $v = Validator::make($a->only(['type', 'street', 'postal_code', 'city', 'country']), [
                'type' => ['required', 'in:abholung,lieferung,sonstige'], 'street' => ['required', 'string', 'max:255'],
                'postal_code' => ['required', 'string', 'size:5'], 'city' => ['required', 'string', 'max:255'], 'country' => ['required', 'size:2'],
            ]);
            $this->assertFalse($v->fails(), 'Adresse '.$a->street.': '.implode(' ', $v->errors()->all()));
        }

        $this->assertGreaterThan(100, Contact::count());
        $this->assertGreaterThan(20, Address::count());
    }

    public function test_genau_ein_hauptansprechpartner_je_kunde(): void
    {
        $this->demo(200);

        foreach (Customer::withTrashed()->with('contacts')->get() as $c) {
            if ($c->contacts->isNotEmpty()) {
                $this->assertSame(1, $c->contacts->where('is_primary', true)->count(), $c->customer_number.' braucht genau einen Hauptansprechpartner.');
            }
        }
    }

    public function test_alle_kundenarten_kommen_vor_und_die_felder_passen_zur_art(): void
    {
        $this->demo(200);

        $arten = $this->all()->groupBy(fn ($c) => $c->type->value);
        $this->assertEqualsCanonicalizing(['firma', 'privat', 'schule', 'verein', 'reisebuero'], $arten->keys()->all());

        foreach ($this->all() as $c) {
            if ($c->type->value === 'privat') {
                $this->assertNotEmpty($c->last_name);
                $this->assertNotEmpty($c->first_name);
                $this->assertNull($c->company_name);
            } else {
                $this->assertNotEmpty($c->company_name);
            }
        }
    }

    public function test_ort_postleitzahl_und_vorwahl_passen_zusammen(): void
    {
        $this->demo(200);
        $orte = (new ReflectionClass(DemoDataGenerator::class))->getReflectionConstant('CITIES')->getValue();

        foreach ($this->all() as $c) {
            $this->assertArrayHasKey($c->city, $orte);
            [$vorwahl, $plz] = $orte[$c->city];
            $this->assertContains($c->postal_code, $plz, "{$c->city}: Postleitzahl passt nicht.");
            $this->assertStringStartsWith($vorwahl.' ', $c->phone, "{$c->city}: Vorwahl passt nicht.");
        }
    }

    public function test_adressen_sind_erfunden_und_koennen_nie_echte_personen_erreichen(): void
    {
        $this->demo(200);

        foreach ($this->all() as $c) {
            $this->assertStringEndsWith('.example', $c->email, 'E-Mail-Adressen müssen auf der reservierten Endung .example enden.');
        }
        foreach (Contact::all() as $c) {
            $this->assertStringEndsWith('.example', $c->email);
        }
    }

    public function test_namen_sind_eindeutig_und_loesen_keine_dublettenwarnung_aus(): void
    {
        $this->demo(200);

        $firmen = $this->all()->pluck('company_name')->filter()->map(fn ($n) => mb_strtolower($n));
        $this->assertSame($firmen->count(), $firmen->unique()->count(), 'Firmennamen müssen eindeutig sein.');

        $privat = $this->all()->filter(fn ($c) => $c->type->value === 'privat')
            ->map(fn ($c) => mb_strtolower($c->first_name.'|'.$c->last_name.'|'.$c->postal_code));
        $this->assertSame($privat->count(), $privat->unique()->count(), 'Privatkunden mit gleichem Namen und gleicher PLZ wären eine Dublette.');
    }

    public function test_archivierte_kunden_sind_dabei(): void
    {
        $this->demo(200);

        $archiviert = Customer::onlyTrashed()->count();
        $this->assertGreaterThan(0, $archiviert);
        $this->assertLessThan(30, $archiviert, 'Nur wenige sollen archiviert sein.');

        $this->admin()->getJson('/api/customers?archived=1')->assertOk()->assertJsonPath('total', $archiviert);
    }

    // ---------- Wiederholbarkeit ----------

    public function test_generator_liefert_je_nummer_immer_dasselbe(): void
    {
        $heute = CarbonImmutable::parse('2026-11-19 12:00');
        $a = new DemoDataGenerator($heute);
        $b = new DemoDataGenerator($heute);

        foreach (range(1, 40) as $i) {
            $this->assertSame(json_encode($a->make($i)), json_encode($b->make($i)), "Kunde {$i} muss bei jedem Lauf gleich sein.");
        }
    }

    public function test_zweiter_lauf_legt_nichts_doppelt_an(): void
    {
        $this->demo(50);
        $ids = Customer::withTrashed()->orderBy('customer_number')->pluck('id')->all();

        $this->artisan('bustotal:demo-daten', ['--anzahl' => 50])
            ->expectsOutputToContain('0 Demo-Kunden angelegt, 50 waren schon vorhanden')
            ->assertSuccessful();

        $this->assertSame(50, Customer::withTrashed()->count());
        $this->assertSame($ids, Customer::withTrashed()->orderBy('customer_number')->pluck('id')->all());
        $this->assertSame(51, (int) NumberRange::where('type', 'kunde')->value('next_number'), 'Ein zweiter Lauf darf keine Nummern verbrauchen.');
    }

    public function test_groessere_anzahl_ergaenzt_nur_und_behaelt_die_ersten(): void
    {
        $this->demo(20);
        $erste = Customer::withTrashed()->orderBy('customer_number')->get(['id', 'customer_number', 'company_name', 'last_name'])->toArray();

        $this->demo(40);

        $this->assertSame(40, Customer::withTrashed()->count());
        $this->assertSame($erste, Customer::withTrashed()->orderBy('customer_number')->limit(20)->get(['id', 'customer_number', 'company_name', 'last_name'])->toArray());
        $this->assertSame('K-00040', Customer::withTrashed()->max('customer_number'));
    }

    public function test_ids_sind_feste_uuids(): void
    {
        $this->demo(10);

        foreach (range(1, 10) as $i) {
            $this->assertTrue(Customer::withTrashed()->whereKey(ImportId::for('demo', 'kunde', $i))->exists());
        }
    }

    // ---------- Protokoll ----------

    public function test_nur_ein_eintrag_im_aenderungsprotokoll(): void
    {
        // Der Seeder im Test-Aufbau hat die Nummernkreise angelegt und dabei schon protokolliert.
        // Gezählt wird deshalb, was der Demo-Lauf selbst dazu beiträgt.
        $vorher = AuditLog::count();

        $this->demo(60);

        $this->assertSame($vorher + 1, AuditLog::count(), 'Ein Demo-Lauf soll genau einen Protokolleintrag erzeugen, nicht einen je Kunde.');
        $this->assertSame(1, AuditLog::where('event', 'demo_data_created')->count());
    }

    // ---------- Entfernen ----------

    public function test_entfernen_trifft_nur_demo_kunden_und_laesst_zaehler_bei_echten_kunden(): void
    {
        $echt = app(CustomerService::class)->create(['type' => 'firma', 'company_name' => 'Echter Kunde GmbH', 'country' => 'DE']);
        $this->demo(20);
        $this->assertSame(21, Customer::withTrashed()->count());

        $this->artisan('bustotal:demo-daten', ['--entfernen' => true])
            ->expectsOutputToContain('20 Demo-Kunden entfernt')
            ->expectsOutputToContain('Nummernzähler bleiben unverändert')
            ->assertSuccessful();

        $this->assertSame(1, Customer::withTrashed()->count());
        $this->assertSame($echt->id, Customer::first()->id);
        $this->assertFalse(Customer::first()->is_demo);
        $this->assertSame(22, (int) NumberRange::where('type', 'kunde')->value('next_number'), 'Mit echten Kunden dürfen Zähler nie zurückgesetzt werden.');
    }

    public function test_entfernen_raeumt_ansprechpartner_und_adressen_mit_ab(): void
    {
        $this->demo(60);
        $this->assertGreaterThan(0, Contact::count());

        $this->artisan('bustotal:demo-daten', ['--entfernen' => true])->assertSuccessful();

        $this->assertSame(0, Customer::withTrashed()->count());
        $this->assertSame(0, Contact::count());
        $this->assertSame(0, Address::count());
    }

    public function test_ohne_kunden_beginnen_die_nummern_wieder_am_anfang(): void
    {
        $this->demo(10);
        $this->artisan('bustotal:demo-daten', ['--entfernen' => true])
            ->expectsOutputToContain('beginnen wieder am Anfang')
            ->assertSuccessful();

        $this->assertSame(1, (int) NumberRange::where('type', 'kunde')->value('next_number'));
        $this->assertSame(10000, (int) NumberRange::where('type', 'debitor')->value('next_number'));

        $this->demo(10);
        $this->assertSame('K-00001', Customer::withTrashed()->min('customer_number'), 'Eine neue Schulung beginnt wieder bei K-00001.');
        $this->assertSame(1, AuditLog::where('event', 'demo_data_removed')->count());
    }

    public function test_entfernen_ohne_demo_kunden_tut_nichts(): void
    {
        $this->artisan('bustotal:demo-daten', ['--entfernen' => true])->expectsOutputToContain('0 Demo-Kunden entfernt')->assertSuccessful();

        $this->assertSame(0, AuditLog::where('event', 'demo_data_removed')->count());
    }

    // ---------- Schutz ----------

    public function test_in_produktiver_umgebung_nur_mit_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('bustotal:demo-daten', ['--anzahl' => 5])->expectsOutputToContain('produktive Umgebung')->assertFailed();
        $this->assertSame(0, Customer::withTrashed()->count());

        $this->artisan('bustotal:demo-daten', ['--anzahl' => 5, '--force' => true])->assertSuccessful();
        $this->assertSame(5, Customer::withTrashed()->count());
    }

    public function test_entfernen_in_produktiver_umgebung_ebenfalls_nur_mit_force(): void
    {
        $this->demo(5);
        $this->app['env'] = 'production';

        $this->artisan('bustotal:demo-daten', ['--entfernen' => true])->assertFailed();
        $this->assertSame(5, Customer::withTrashed()->count());
    }

    public function test_anzahl_wird_geprueft(): void
    {
        $this->artisan('bustotal:demo-daten', ['--anzahl' => 0])->assertFailed();
        $this->artisan('bustotal:demo-daten', ['--anzahl' => 99999])->assertFailed();
        $this->assertSame(0, Customer::withTrashed()->count());
    }

    public function test_formular_kann_die_demo_markierung_nicht_setzen(): void
    {
        $this->admin()->postJson('/api/customers', ['type' => 'firma', 'company_name' => 'Hacker GmbH', 'country' => 'DE', 'is_demo' => true])
            ->assertCreated()->assertJsonPath('is_demo', false);
    }

    // ---------- Nutzbar in der Anwendung ----------

    public function test_demo_kunden_lassen_sich_in_der_anwendung_wie_echte_benutzen(): void
    {
        $this->demo(200);
        $archiviert = Customer::onlyTrashed()->count();

        // Liste mit Seiten
        $this->admin()->getJson('/api/customers')->assertOk()
            ->assertJsonPath('total', 200 - $archiviert)->assertJsonPath('per_page', 20)->assertJsonCount(20, 'data');

        // Suche
        $kunde = Customer::where('is_demo', true)->whereNotNull('company_name')->first();
        $treffer = $this->admin()->getJson('/api/customers?q='.urlencode($kunde->company_name))->assertOk()->json('data');
        $this->assertContains($kunde->id, array_column($treffer, 'id'));

        // Einzelansicht mit Ansprechpartnern und Markierung
        $this->admin()->getJson("/api/customers/{$kunde->id}")->assertOk()->assertJsonPath('is_demo', true)->assertJsonStructure(['contacts', 'addresses']);

        // Dublettenwarnung für einen vorhandenen Namen
        $this->admin()->getJson('/api/customers/duplicates?company_name='.urlencode($kunde->company_name))->assertOk()->assertJsonCount(1);

        // Startseite
        $this->admin()->getJson('/api/dashboard/recent-customers')->assertOk()->assertJsonCount(5);
    }

    public function test_aendern_eines_demo_kunden_wird_normal_protokolliert_und_behaelt_die_markierung(): void
    {
        $this->demo(10);
        $c = Customer::where('is_demo', true)->first();

        $this->admin()->putJson("/api/customers/{$c->id}", [
            'type' => $c->type->value, 'company_name' => $c->company_name ?? 'x', 'last_name' => $c->last_name, 'country' => 'DE', 'city' => 'Geändert',
        ])->assertOk();

        $this->assertTrue($c->fresh()->is_demo);
        $this->assertSame(1, AuditLog::where('event', 'updated')->where('auditable_id', $c->id)->count());
    }

    public function test_kunden_anlegen_in_der_anwendung_funktioniert_nach_demo_daten_weiter(): void
    {
        $this->demo(20);

        $this->admin()->postJson('/api/customers', ['type' => 'firma', 'company_name' => 'Nach Demo GmbH', 'country' => 'DE'])
            ->assertCreated()->assertJsonPath('customer_number', 'K-00021')->assertJsonPath('debitor_number', 10020)->assertJsonPath('is_demo', false);
    }
}
