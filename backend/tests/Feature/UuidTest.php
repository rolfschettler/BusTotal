<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\NumberRange;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\ImportId;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UuidTest extends TestCase
{
    use RefreshDatabase;

    /** UUID Version 7: an Stelle 15 steht eine 7, an Stelle 20 eine 8, 9, a oder b. */
    private const V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private const V5 = '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    // ---------- Primärschlüssel ----------

    public function test_alle_fachlichen_tabellen_vergeben_uuid_version_7(): void
    {
        $customer = Customer::factory()->create();
        $objects = [
            User::factory()->create(),
            $customer,
            $customer->contacts()->create(['last_name' => 'Muster']),
            $customer->addresses()->create(['type' => 'abholung', 'street' => 'A 1', 'postal_code' => '12345', 'city' => 'B', 'country' => 'DE']),
            CompanySetting::current(),
            TaxRate::first(),
            NumberRange::first(),
        ];

        foreach ($objects as $o) {
            $this->assertMatchesRegularExpression(self::V7, $o->getKey(), get_class($o).' hat keine UUID Version 7.');
        }
    }

    public function test_uuids_sind_zeitlich_geordnet(): void
    {
        $ids = [];
        foreach (range(1, 4) as $i) {
            $ids[] = Customer::factory()->create()->id;
            usleep(2000); // 2 ms: Version 7 ordnet nach Millisekunden
        }

        $sorted = $ids;
        sort($sorted);
        $this->assertSame($ids, $sorted, 'Neue IDs müssen größer sein als ältere (gut für den Datenbank-Index).');
    }

    public function test_das_protokoll_behaelt_seine_fortlaufende_zahl_verweist_aber_per_uuid(): void
    {
        $c = Customer::factory()->create();

        $log = AuditLog::where('event', 'created')->where('auditable_id', $c->id)->first();
        $this->assertIsInt($log->id);
        $this->assertSame($c->id, $log->auditable_id);
    }

    // ---------- Schnittstelle ----------

    public function test_kunde_ueber_uuid_erreichbar_alte_zahl_ist_unbekannt(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $c = Customer::factory()->create();

        $this->actingAs($admin, 'sanctum')->getJson("/api/customers/{$c->id}")->assertOk()->assertJsonPath('id', $c->id);
        $this->actingAs($admin, 'sanctum')->getJson('/api/customers/1')->assertNotFound();
    }

    public function test_ids_kommen_in_der_antwort_als_text(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        Customer::factory()->create();

        $id = $this->actingAs($admin, 'sanctum')->getJson('/api/customers')->json('data.0.id');
        $this->assertIsString($id);
        $this->assertMatchesRegularExpression(self::V7, $id);
    }

    public function test_dublettenpruefung_verlangt_eine_uuid(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/customers/duplicates?ignore=17')->assertStatus(422)->assertJsonValidationErrors('ignore');
    }

    public function test_anmeldung_und_token_funktionieren_mit_uuid_benutzern(): void
    {
        $u = User::factory()->create(['email' => 'u@firma.test', 'password' => 'Geheim12345', 'role' => Role::Disponent]);
        $token = $this->postJson('/api/login', ['email' => 'u@firma.test', 'password' => 'Geheim12345'])->assertOk()->json('token');

        $this->assertSame($u->id, $u->tokens()->first()->tokenable_id);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('id', $u->id);
    }

    // ---------- Datenübernahme ----------

    public function test_import_id_ist_fest_und_unterscheidet_quelle_art_und_nummer(): void
    {
        $a = ImportId::for('altsystem', 'kunde', '4711');

        $this->assertMatchesRegularExpression(self::V5, $a);
        $this->assertSame($a, ImportId::for('altsystem', 'kunde', '4711'), 'Dieselbe Eingabe muss immer dieselbe UUID ergeben.');
        $this->assertSame($a, ImportId::for('altsystem', 'kunde', 4711), 'Zahl und Text sind dasselbe.');
        $this->assertSame($a, ImportId::for('  AltSystem ', 'Kunde', ' 4711 '), 'Groß-/Kleinschreibung und Leerzeichen spielen keine Rolle.');

        $this->assertNotSame($a, ImportId::for('anderes-system', 'kunde', '4711'));
        $this->assertNotSame($a, ImportId::for('altsystem', 'fahrzeug', '4711'));
        $this->assertNotSame($a, ImportId::for('altsystem', 'kunde', '4712'));
    }

    /**
     * Festgehaltener Wert (einmal berechnet und hier eingetragen). Ändert jemand den Namensraum oder die
     * Berechnung in ImportId, würden spätere Importe alle Datensätze als "neu" ansehen und Dubletten anlegen.
     * Schlägt dieser Test an, ist das also kein Schönheitsfehler. Dann nicht den Wert hier anpassen, sondern
     * die Änderung an ImportId zurücknehmen.
     */
    public function test_import_id_aendert_sich_nie(): void
    {
        $this->assertSame('e5039e61-0597-50c3-a1b4-b092bf52dcd8', ImportId::for('altsystem', 'kunde', '4711'));
    }

    public function test_wiederholter_import_legt_keine_dubletten_an(): void
    {
        $id = ImportId::for('altsystem', 'kunde', '4711');

        foreach (['Erster Lauf GmbH', 'Zweiter Lauf GmbH (korrigiert)'] as $name) {
            Customer::unguarded(fn () => Customer::updateOrCreate(['id' => $id], [
                'customer_number' => 'K-00042', 'type' => 'firma', 'company_name' => $name, 'country' => 'DE',
            ]));
        }

        $this->assertSame(1, Customer::count());
        $this->assertSame($id, Customer::first()->id, 'Die feste ID muss erhalten bleiben.');
        $this->assertSame('Zweiter Lauf GmbH (korrigiert)', Customer::first()->company_name);
    }

    public function test_ansprechpartner_lassen_sich_mit_vorab_bekannter_kunden_id_vorbereiten(): void
    {
        // Die ID des Kunden steht fest, bevor er gespeichert ist. Der Ansprechpartner wird fertig vorbereitet.
        $kundenId = ImportId::for('altsystem', 'kunde', '4711');
        $kontakt = ['customer_id' => $kundenId, 'last_name' => 'Aus Altsystem'];

        Customer::unguarded(fn () => Customer::create([
            'id' => $kundenId, 'customer_number' => 'K-00001', 'type' => 'firma', 'company_name' => 'Altkunde', 'country' => 'DE',
        ]));
        Contact::unguarded(fn () => Contact::create($kontakt));

        $this->assertSame('Aus Altsystem', Customer::find($kundenId)->contacts()->first()->last_name);
    }

    public function test_ohne_aufheben_der_sperre_wird_die_feste_id_verworfen(): void
    {
        // Das ist die Falle, vor der der Kommentar in ImportId warnt. Der Test hält sie fest.
        // Steuersätze eignen sich dafür, weil dort nur die "id" gesperrt ist.
        $id = ImportId::for('altsystem', 'steuersatz', '1');

        $verworfen = TaxRate::create(['id' => $id, 'name' => 'Ohne Aufheben', 'rate' => 5]);
        $this->assertNotSame($id, $verworfen->id, 'Ohne unguarded() wird die feste ID still ersetzt.');

        $erhalten = TaxRate::unguarded(fn () => TaxRate::create(['id' => $id, 'name' => 'Mit Aufheben', 'rate' => 5]));
        $this->assertSame($id, $erhalten->id);
    }
}
