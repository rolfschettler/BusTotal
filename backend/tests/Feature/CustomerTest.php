<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
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

    private function firma(array $override = []): array
    {
        return array_merge([
            'type' => 'firma', 'company_name' => 'Reisebüro Sonnenschein', 'email' => 'info@sonnenschein.test',
            'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München', 'country' => 'DE',
        ], $override);
    }

    // ---------- Zugriff ----------

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->getJson('/api/customers')->assertUnauthorized();
    }

    public function test_fahrer_hat_keinen_zugriff(): void
    {
        $this->as(Role::Fahrer)->getJson('/api/customers')->assertForbidden();
        $this->as(Role::Fahrer)->postJson('/api/customers', $this->firma())->assertForbidden();
    }

    public function test_disponent_und_buchhaltung_duerfen_kunden_anlegen(): void
    {
        $this->as(Role::Disponent)->postJson('/api/customers', $this->firma())->assertCreated();
        $this->as(Role::Buchhaltung)->postJson('/api/customers', $this->firma(['company_name' => 'Zweite GmbH', 'email' => null]))->assertCreated();
    }

    // ---------- Anlegen ----------

    public function test_nummern_werden_automatisch_und_fortlaufend_vergeben(): void
    {
        $this->as(Role::Admin)->postJson('/api/customers', $this->firma())
            ->assertCreated()
            ->assertJsonPath('customer_number', 'K-00001')
            ->assertJsonPath('debitor_number', 10000)
            ->assertJsonPath('display_name', 'Reisebüro Sonnenschein');

        $this->as(Role::Admin)->postJson('/api/customers', $this->firma(['company_name' => 'Anderer Verein e.V.', 'type' => 'verein']))
            ->assertJsonPath('customer_number', 'K-00002')
            ->assertJsonPath('debitor_number', 10001);
    }

    public function test_nummern_koennen_nicht_per_formular_gesetzt_werden(): void
    {
        $this->as(Role::Admin)->postJson('/api/customers', $this->firma(['customer_number' => 'HACK-1', 'debitor_number' => 1]))
            ->assertCreated()->assertJsonPath('customer_number', 'K-00001');
    }

    public function test_firma_braucht_firmennamen_privatkunde_braucht_nachnamen(): void
    {
        $this->as(Role::Admin)->postJson('/api/customers', $this->firma(['company_name' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('company_name');

        $this->as(Role::Admin)->postJson('/api/customers', ['type' => 'privat', 'country' => 'DE'])
            ->assertStatus(422)->assertJsonValidationErrors('last_name');

        $this->as(Role::Admin)->postJson('/api/customers', ['type' => 'privat', 'last_name' => 'Müller', 'first_name' => 'Anna', 'country' => 'DE'])
            ->assertCreated()->assertJsonPath('display_name', 'Anna Müller');
    }

    public function test_fehlgeschlagenes_anlegen_verbraucht_keine_nummer(): void
    {
        $this->as(Role::Admin)->postJson('/api/customers', $this->firma(['email' => 'kein-email']))->assertStatus(422);
        $this->as(Role::Admin)->postJson('/api/customers', $this->firma())->assertJsonPath('customer_number', 'K-00001');
    }

    // ---------- Suche ----------

    public function test_suche_und_filter(): void
    {
        Customer::factory()->create(['company_name' => 'Alpen Tours', 'city' => 'Innsbruck']);
        Customer::factory()->create(['company_name' => 'Nordsee Reisen', 'city' => 'Husum']);
        Customer::factory()->privat()->create(['first_name' => 'Anna', 'last_name' => 'Alpenrose']);

        $this->as(Role::Admin)->getJson('/api/customers?q=alpen')->assertOk()->assertJsonPath('total', 2);
        $this->as(Role::Admin)->getJson('/api/customers?q=Husum')->assertJsonPath('total', 1);
        $this->as(Role::Admin)->getJson('/api/customers?type=privat')->assertJsonPath('total', 1);
        $this->as(Role::Admin)->getJson('/api/customers?q=%25')->assertJsonPath('total', 0); // "%" ist kein Platzhalter
    }

    // ---------- Dubletten ----------

    public function test_dublette_per_email_und_firmenname(): void
    {
        Customer::factory()->create(['company_name' => 'Alpen Tours', 'email' => 'Info@Alpen.test']);

        $this->as(Role::Admin)->getJson('/api/customers/duplicates?email=info@alpen.test')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.reason', 'Gleiche E-Mail-Adresse');

        $this->as(Role::Admin)->getJson('/api/customers/duplicates?company_name=ALPEN TOURS')
            ->assertJsonCount(1)->assertJsonPath('0.reason', 'Gleicher Firmenname');

        $this->as(Role::Admin)->getJson('/api/customers/duplicates?company_name=Ganz Neu')->assertJsonCount(0);
    }

    public function test_dublette_per_name_und_plz_ignoriert_den_eigenen_datensatz(): void
    {
        $c = Customer::factory()->privat()->create(['first_name' => 'Anna', 'last_name' => 'Müller', 'postal_code' => '80331']);

        $url = '/api/customers/duplicates?first_name=Anna&last_name=Müller&postal_code=80331';
        $this->as(Role::Admin)->getJson($url)->assertJsonCount(1);
        $this->as(Role::Admin)->getJson($url.'&ignore='.$c->id)->assertJsonCount(0);
    }

    // ---------- Ändern, Archivieren ----------

    public function test_aendern_wird_im_audit_log_protokolliert(): void
    {
        $c = Customer::factory()->create(['city' => 'Berlin']);

        $this->as(Role::Admin)->putJson("/api/customers/{$c->id}", $this->firma(['company_name' => $c->company_name, 'city' => 'Hamburg']))
            ->assertOk()->assertJsonPath('city', 'Hamburg');

        $log = AuditLog::where('event', 'updated')->where('auditable_id', $c->id)->latest('id')->first();
        $this->assertSame('Berlin', $log->old_values['city']);
        $this->assertSame('Hamburg', $log->new_values['city']);
    }

    public function test_loeschen_archiviert_nur_und_admin_kann_wiederherstellen(): void
    {
        $c = Customer::factory()->create();

        $this->as(Role::Disponent)->deleteJson("/api/customers/{$c->id}")->assertOk();
        $this->assertSoftDeleted('customers', ['id' => $c->id]);

        $this->as(Role::Disponent)->getJson('/api/customers')->assertJsonPath('total', 0);
        $this->as(Role::Disponent)->getJson('/api/customers?archived=1')->assertJsonPath('total', 1);

        $this->as(Role::Disponent)->postJson("/api/customers/{$c->id}/restore")->assertForbidden();
        $this->as(Role::Admin)->postJson("/api/customers/{$c->id}/restore")->assertOk();
        $this->assertNotSoftDeleted('customers', ['id' => $c->id]);
    }

    // ---------- Ansprechpartner & Adressen ----------

    public function test_nur_ein_hauptansprechpartner(): void
    {
        $c = Customer::factory()->create();
        $a = $this->as(Role::Admin)->postJson("/api/customers/{$c->id}/contacts", ['last_name' => 'Erster', 'is_primary' => true])
            ->assertCreated()->json('id');
        $this->as(Role::Admin)->postJson("/api/customers/{$c->id}/contacts", ['last_name' => 'Zweiter', 'is_primary' => true])->assertCreated();

        $this->assertFalse((bool) $c->contacts()->find($a)->is_primary);
        $this->assertSame(1, $c->contacts()->where('is_primary', true)->count());
    }

    public function test_ansprechpartner_eines_anderen_kunden_ist_nicht_erreichbar(): void
    {
        $c1 = Customer::factory()->create();
        $c2 = Customer::factory()->create();
        $contact = $c1->contacts()->create(['last_name' => 'Müller']);

        $this->as(Role::Admin)->deleteJson("/api/customers/{$c2->id}/contacts/{$contact->id}")->assertNotFound();
        $this->as(Role::Admin)->deleteJson("/api/customers/{$c1->id}/contacts/{$contact->id}")->assertOk();
    }

    public function test_adresse_anlegen_und_validieren(): void
    {
        $c = Customer::factory()->create();

        $this->as(Role::Admin)->postJson("/api/customers/{$c->id}/addresses", ['type' => 'abholung'])
            ->assertStatus(422)->assertJsonValidationErrors(['street', 'postal_code', 'city']);

        $this->as(Role::Admin)->postJson("/api/customers/{$c->id}/addresses", [
            'type' => 'abholung', 'label' => 'Schulhof', 'street' => 'Schulweg 3', 'postal_code' => '12345', 'city' => 'Dorf', 'country' => 'DE',
        ])->assertCreated();
        $this->assertSame(1, $c->addresses()->count());
    }

    // ---------- DSGVO ----------

    public function test_export_enthaelt_alle_daten_und_wird_protokolliert(): void
    {
        $c = Customer::factory()->create(['company_name' => 'Export GmbH']);
        $c->contacts()->create(['last_name' => 'Kontakt']);

        $this->as(Role::Buchhaltung)->getJson("/api/customers/{$c->id}/export")
            ->assertOk()
            ->assertJsonPath('kunde.company_name', 'Export GmbH')
            ->assertJsonPath('ansprechpartner.0.last_name', 'Kontakt');

        $this->assertDatabaseHas('audit_logs', ['event' => 'exported', 'auditable_id' => $c->id]);
    }

    public function test_anonymisieren_nur_admin_und_nummern_bleiben(): void
    {
        $c = Customer::factory()->create(['company_name' => 'Geheim GmbH', 'email' => 'geheim@test.de', 'customer_number' => 'K-00042', 'debitor_number' => 10042]);
        $c->contacts()->create(['last_name' => 'Müller']);

        $this->as(Role::Disponent)->postJson("/api/customers/{$c->id}/anonymize")->assertForbidden();
        $this->as(Role::Admin)->postJson("/api/customers/{$c->id}/anonymize")->assertOk();

        $c = Customer::withTrashed()->find($c->id);
        $this->assertSame('Anonymisiert', $c->company_name);
        $this->assertNull($c->email);
        $this->assertSame('K-00042', $c->customer_number);
        $this->assertSame(10042, (int) $c->debitor_number);
        $this->assertTrue($c->trashed());
        $this->assertSame(0, $c->contacts()->count());

        // Im Protokoll steht, DASS anonymisiert wurde – aber ohne die alten Daten.
        $entry = AuditLog::where('event', 'anonymized')->where('auditable_id', $c->id)->first();
        $this->assertNotNull($entry);
        $this->assertNull($entry->old_values);
    }
}
