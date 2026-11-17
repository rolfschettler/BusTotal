<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);

        // Vier Kunden, bewusst durcheinander angelegt. Einer ohne E-Mail und Ort.
        Customer::factory()->create(['customer_number' => 'K-00002', 'company_name' => 'Zeta Reisen', 'city' => 'Bonn', 'postal_code' => '53111', 'email' => 'zeta@x.test', 'phone' => '0228 1', 'type' => 'firma']);
        Customer::factory()->create(['customer_number' => 'K-00001', 'company_name' => 'Alpha Tours', 'city' => 'Aachen', 'postal_code' => '52062', 'email' => 'alpha@x.test', 'phone' => '0241 2', 'type' => 'verein']);
        Customer::factory()->create(['customer_number' => 'K-00004', 'company_name' => 'Mittel GmbH', 'city' => null, 'postal_code' => null, 'email' => null, 'phone' => null, 'type' => 'schule']);
        Customer::factory()->create(['customer_number' => 'K-00003', 'company_name' => 'Beta Busse', 'city' => 'Berlin', 'postal_code' => '10115', 'email' => 'beta@x.test', 'phone' => '030 3', 'type' => 'firma']);
    }

    private function names(string $query): array
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        return collect($this->actingAs($admin, 'sanctum')->getJson('/api/customers'.$query)->assertOk()->json('data'))
            ->pluck('company_name')->all();
    }

    public function test_standard_ist_name_aufsteigend(): void
    {
        $this->assertSame(['Alpha Tours', 'Beta Busse', 'Mittel GmbH', 'Zeta Reisen'], $this->names(''));
    }

    public function test_nach_name_absteigend(): void
    {
        $this->assertSame(['Zeta Reisen', 'Mittel GmbH', 'Beta Busse', 'Alpha Tours'], $this->names('?sort=name&dir=desc'));
    }

    public function test_nach_nummer(): void
    {
        $this->assertSame(['Alpha Tours', 'Zeta Reisen', 'Beta Busse', 'Mittel GmbH'], $this->names('?sort=number&dir=asc'));
        $this->assertSame(['Mittel GmbH', 'Beta Busse', 'Zeta Reisen', 'Alpha Tours'], $this->names('?sort=number&dir=desc'));
    }

    public function test_nach_ort_leere_werte_stehen_immer_am_ende(): void
    {
        $this->assertSame(['Alpha Tours', 'Beta Busse', 'Zeta Reisen', 'Mittel GmbH'], $this->names('?sort=city&dir=asc'));
        $this->assertSame(['Zeta Reisen', 'Beta Busse', 'Alpha Tours', 'Mittel GmbH'], $this->names('?sort=city&dir=desc'));
    }

    public function test_nach_email_und_telefon_leere_werte_am_ende(): void
    {
        $this->assertSame(['Alpha Tours', 'Beta Busse', 'Zeta Reisen', 'Mittel GmbH'], $this->names('?sort=email'));
        // Als Text sortiert: 0228 (Zeta) < 0241 (Alpha) < 030 (Beta)
        $this->assertSame(['Zeta Reisen', 'Alpha Tours', 'Beta Busse', 'Mittel GmbH'], $this->names('?sort=phone'));
        $this->assertSame(['Zeta Reisen', 'Beta Busse', 'Alpha Tours', 'Mittel GmbH'], $this->names('?sort=email&dir=desc'));
    }

    public function test_nach_art(): void
    {
        // firma < schule < verein; bei Gleichstand (zwei Firmen) entscheidet die Kundennummer
        $this->assertSame(['Zeta Reisen', 'Beta Busse', 'Mittel GmbH', 'Alpha Tours'], $this->names('?sort=type'));
    }

    public function test_bei_gleichstand_entscheidet_die_kundennummer_beim_blaettern_ohne_doppelte(): void
    {
        // 45 Kunden mit demselben Namen: Die Reihenfolge muss trotzdem eindeutig sein.
        foreach (range(1, 45) as $i) {
            Customer::factory()->create(['customer_number' => 'G-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'company_name' => 'Gleicher Name']);
        }
        $admin = User::factory()->create(['role' => Role::Admin]);

        $ids = [];
        foreach ([1, 2, 3] as $page) {
            $ids = array_merge($ids, collect($this->actingAs($admin, 'sanctum')->getJson("/api/customers?q=Gleicher&page={$page}")->json('data'))->pluck('id')->all());
        }

        $this->assertCount(45, $ids);
        $this->assertCount(45, array_unique($ids), 'Kein Kunde darf auf zwei Seiten erscheinen.');
    }

    public function test_sortierung_wirkt_zusammen_mit_suche_und_filter(): void
    {
        $this->assertSame(['Zeta Reisen', 'Beta Busse'], $this->names('?type=firma&sort=name&dir=desc'));
        $this->assertSame(['Alpha Tours'], $this->names('?q=alpha&sort=city'));
    }

    public function test_unbekannte_sortierung_wird_abgelehnt(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/customers?sort=password')->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->actingAs($admin, 'sanctum')->getJson('/api/customers?sort=name&dir=sideways')->assertStatus(422)->assertJsonValidationErrors('dir');
    }

    public function test_sql_einschleusen_ueber_die_sortierung_ist_wirkungslos(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        foreach (['name; DROP TABLE customers', 'name,(SELECT 1)', "name' OR '1'='1", '(SELECT SLEEP(5))'] as $evil) {
            $this->actingAs($admin, 'sanctum')->getJson('/api/customers?sort='.urlencode($evil))->assertStatus(422);
            $this->actingAs($admin, 'sanctum')->getJson('/api/customers?sort=name&dir='.urlencode($evil))->assertStatus(422);
        }

        $this->assertSame(4, Customer::count(), 'Die Tabelle muss unversehrt sein.');
    }

    public function test_fehlermeldung_ist_deutsch(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/customers?sort=x')
            ->assertJsonPath('errors.sort.0', 'Die Auswahl für Sortierung ist ungültig.');
    }

    public function test_leere_sortierparameter_fallen_auf_den_standard_zurueck(): void
    {
        $this->assertSame(['Alpha Tours', 'Beta Busse', 'Mittel GmbH', 'Zeta Reisen'], $this->names('?sort=&dir='));
    }

    public function test_archivierte_lassen_sich_ebenfalls_sortieren(): void
    {
        Customer::where('company_name', 'Zeta Reisen')->first()->delete();
        Customer::where('company_name', 'Alpha Tours')->first()->delete();

        $this->assertSame(['Zeta Reisen', 'Alpha Tours'], $this->names('?archived=1&sort=name&dir=desc'));
    }
}
