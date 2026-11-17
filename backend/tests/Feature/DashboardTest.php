<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function done(array $json): array
    {
        return collect($json['items'])->pluck('done', 'key')->all();
    }

    // ---------- Rechte ----------

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->getJson('/api/dashboard/setup')->assertUnauthorized();
        $this->getJson('/api/dashboard/recent-customers')->assertUnauthorized();
    }

    public function test_einrichtung_nur_fuer_den_admin(): void
    {
        foreach ([Role::Disponent, Role::Buchhaltung, Role::Fahrer] as $role) {
            $this->as($role)->getJson('/api/dashboard/setup')->assertForbidden();
        }
        $this->as(Role::Admin)->getJson('/api/dashboard/setup')->assertOk();
    }

    public function test_zuletzt_bearbeitete_kunden_nicht_fuer_fahrer(): void
    {
        $this->as(Role::Fahrer)->getJson('/api/dashboard/recent-customers')->assertForbidden();
        foreach ([Role::Admin, Role::Disponent, Role::Buchhaltung] as $role) {
            $this->as($role)->getJson('/api/dashboard/recent-customers')->assertOk();
        }
    }

    // ---------- Einrichtungs-Checkliste ----------

    public function test_neue_firma_hat_nichts_erledigt(): void
    {
        $res = $this->as(Role::Admin)->getJson('/api/dashboard/setup')->assertOk()
            ->assertJsonPath('done', 0)->assertJsonPath('total', 5);

        $this->assertSame(
            ['company' => false, 'tax' => false, 'bank' => false, 'logo' => false, 'customer' => false],
            $this->done($res->json()),
        );
    }

    public function test_punkte_werden_automatisch_als_erledigt_erkannt(): void
    {
        CompanySetting::current()->update([
            'name' => 'Muster Reisen GmbH', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München',
            'vat_id' => 'DE123456789', 'iban' => 'DE89370400440532013000',
        ]);
        Customer::factory()->create();

        $res = $this->as(Role::Admin)->getJson('/api/dashboard/setup')->assertOk()->assertJsonPath('done', 4);

        $this->assertSame(
            ['company' => true, 'tax' => true, 'bank' => true, 'logo' => false, 'customer' => true],
            $this->done($res->json()),
        );
    }

    public function test_firmenname_allein_reicht_nicht(): void
    {
        CompanySetting::current()->update(['name' => 'Muster Reisen GmbH']);

        $res = $this->as(Role::Admin)->getJson('/api/dashboard/setup')->assertOk();
        $this->assertFalse($this->done($res->json())['company']);
    }

    public function test_logo_wird_als_erledigt_erkannt(): void
    {
        CompanySetting::current()->forceFill(['logo_path' => 'logos/x.png'])->save();

        $res = $this->as(Role::Admin)->getJson('/api/dashboard/setup')->assertOk();
        $this->assertTrue($this->done($res->json())['logo']);
    }

    public function test_jeder_punkt_nennt_ziel_und_hilfethema(): void
    {
        $items = $this->as(Role::Admin)->getJson('/api/dashboard/setup')->json('items');

        foreach ($items as $i) {
            $this->assertStringStartsWith('/', $i['route']);
            $this->assertStringContainsString('#', $i['help']);
        }
    }

    // ---------- Zuletzt bearbeitete Kunden ----------

    public function test_letzte_kunden_sind_sortiert_und_auf_fuenf_begrenzt(): void
    {
        foreach (range(1, 7) as $i) {
            Customer::factory()->create(['company_name' => "Kunde {$i}", 'updated_at' => now()->subHours(10 - $i)]);
        }

        $res = $this->as(Role::Disponent)->getJson('/api/dashboard/recent-customers')->assertOk()->assertJsonCount(5);

        $this->assertSame(
            ['Kunde 7', 'Kunde 6', 'Kunde 5', 'Kunde 4', 'Kunde 3'],
            collect($res->json())->pluck('display_name')->all(),
        );
    }

    public function test_geaenderter_kunde_rueckt_nach_oben(): void
    {
        $alt = Customer::factory()->create(['company_name' => 'Alt', 'updated_at' => now()->subDays(5)]);
        Customer::factory()->create(['company_name' => 'Neuer', 'updated_at' => now()->subDay()]);

        $alt->update(['city' => 'Neustadt']);

        $this->assertSame('Alt', $this->as(Role::Admin)->getJson('/api/dashboard/recent-customers')->json('0.display_name'));
    }

    public function test_archivierte_kunden_erscheinen_nicht(): void
    {
        Customer::factory()->create(['company_name' => 'Aktiv']);
        Customer::factory()->create(['company_name' => 'Archiviert'])->delete();

        $this->as(Role::Admin)->getJson('/api/dashboard/recent-customers')
            ->assertJsonCount(1)->assertJsonPath('0.display_name', 'Aktiv');
    }

    public function test_antwort_enthaelt_keine_privaten_felder(): void
    {
        Customer::factory()->create(['email' => 'privat@test.de', 'notes' => 'geheime Notiz']);

        $row = $this->as(Role::Admin)->getJson('/api/dashboard/recent-customers')->json('0');

        $this->assertArrayNotHasKey('email', $row);
        $this->assertArrayNotHasKey('notes', $row);
    }
}
