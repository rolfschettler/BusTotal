<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Sortierbare Spalten der Belegliste (Tabellen-Muster): feste Spaltenliste, leere Werte am Ende, stabile Reihenfolge. */
class DocumentSortTest extends TestCase
{
    use RefreshDatabase;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(SettingsSeeder::class);
        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457',
            'city' => 'Hamburg', 'tax_number' => '12/345/67890', 'payment_term_days' => 14,
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::Buchhaltung]), 'sanctum');

        $zeta = Customer::factory()->create(['company_name' => 'Zeta Reisen']);
        $alpha = Customer::factory()->create(['company_name' => 'Alpha Tours']);

        // Alpha: Rechnung 2000 netto, festgeschrieben. Zeta: Angebot 1000 netto, festgeschrieben. Zeta: Entwurf ohne Nummer.
        $this->ids['rechnung'] = $this->make('rechnung', $alpha, '2000', true);
        $this->ids['angebot'] = $this->make('angebot', $zeta, '1000', true);
        $this->ids['entwurf'] = $this->make('angebot', $zeta, '3000', false);
    }

    private function make(string $type, Customer $c, string $price, bool $finalize): string
    {
        $id = $this->postJson('/api/documents', [
            'type' => $type, 'customer_id' => $c->id, 'service_date_from' => '2027-05-10',
            'lines' => [['title' => 'Busfahrt', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => $price, 'tax_rate' => 19]],
        ])->assertCreated()->json('id');
        if ($finalize) {
            $this->postJson("/api/documents/{$id}/finalize")->assertOk();
        }

        return $id;
    }

    private function order(string $query): array
    {
        $ids = collect($this->getJson('/api/documents'.$query)->assertOk()->json('data'))->pluck('id')->all();

        return array_map(fn ($id) => array_search($id, $this->ids), $ids);
    }

    public function test_standard_ist_datum_absteigend_mit_entwurf_oben(): void
    {
        $this->assertSame(['entwurf', 'angebot', 'rechnung'], $this->order(''));
        $this->assertSame(['entwurf', 'angebot', 'rechnung'], $this->order('?sort=date&dir=desc'));
    }

    public function test_datum_aufsteigend_stellt_den_entwurf_ans_ende(): void
    {
        $this->assertSame('entwurf', $this->order('?sort=date&dir=asc')[2]);
    }

    public function test_nummer_leere_werte_stehen_in_beiden_richtungen_am_ende(): void
    {
        $this->assertSame('entwurf', $this->order('?sort=number&dir=asc')[2]);
        $this->assertSame('entwurf', $this->order('?sort=number&dir=desc')[2]);
    }

    public function test_betrag_art_kunde_und_stand(): void
    {
        $this->assertSame(['angebot', 'rechnung', 'entwurf'], $this->order('?sort=gross&dir=asc'));
        $this->assertSame(['entwurf', 'rechnung', 'angebot'], $this->order('?sort=gross&dir=desc'));
        // Geschäftsgang: Angebote vor Rechnungen
        $this->assertSame('rechnung', $this->order('?sort=type&dir=asc')[2]);
        $this->assertSame('rechnung', $this->order('?sort=customer&dir=asc')[0]);       // Alpha zuerst
        $this->assertSame('rechnung', $this->order('?sort=customer&dir=desc')[2]);
        $this->assertSame('entwurf', $this->order('?sort=status&dir=asc')[0]);           // entwurf < festgeschrieben
    }

    public function test_sortierung_wirkt_mit_suche_und_filter(): void
    {
        $this->assertSame(['angebot', 'entwurf'], $this->order('?q=Zeta&sort=gross&dir=asc'));
        $this->assertSame(['entwurf'], $this->order('?status=entwurf&sort=customer'));
    }

    public function test_unbekannte_sortierung_wird_abgelehnt_und_sql_kommt_nicht_durch(): void
    {
        $this->getJson('/api/documents?sort=password')->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->getJson('/api/documents?sort=date&dir=sideways')->assertStatus(422)->assertJsonValidationErrors('dir');
        foreach (['id; DROP TABLE documents', 'number,(SELECT 1)', 'c.id'] as $evil) {
            $this->getJson('/api/documents?sort='.urlencode($evil))->assertStatus(422);
            $this->getJson('/api/documents?sort=date&dir='.urlencode($evil))->assertStatus(422);
        }
    }
}
