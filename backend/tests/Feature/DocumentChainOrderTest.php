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

/** Liste „je Kunde“: Belege eines Kunden untereinander, Ketten in der Reihenfolge ihrer Entstehung. */
class DocumentChainOrderTest extends TestCase
{
    use RefreshDatabase;

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
    }

    private function angebot(Customer $c): string
    {
        return $this->postJson('/api/documents', [
            'type' => 'angebot', 'customer_id' => $c->id, 'service_date_from' => '2027-05-10',
            'lines' => [['title' => 'Busfahrt', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '1000', 'tax_rate' => 19]],
        ])->assertCreated()->json('id');
    }

    private function folge(string $id, string $type): string
    {
        return $this->postJson("/api/documents/{$id}/convert", ['type' => $type])->assertCreated()->json('id');
    }

    public function test_je_kunde_stehen_die_belegketten_untereinander(): void
    {
        $zeta = Customer::factory()->create(['company_name' => 'Zeta Reisen']);
        $alpha = Customer::factory()->create(['company_name' => 'Alpha Tours']);

        $z1 = $this->angebot($zeta);
        $a1 = $this->angebot($alpha);
        $z2 = $this->angebot($zeta);              // zweite Kette des Kunden, entsteht NACH Alpha
        $this->postJson("/api/documents/{$z1}/finalize")->assertOk();
        $zAb = $this->folge($z1, 'auftragsbestaetigung');

        $res = $this->getJson('/api/documents?order=chain')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();

        // Alpha vor Zeta; bei Zeta die erste Kette (Angebot → Auftragsbestätigung) geschlossen vor der zweiten.
        $this->assertSame([$a1, $z1, $zAb, $z2], $ids);
        $this->assertSame([0, 0, 1, 0], collect($res->json('data'))->pluck('chain_depth')->all());
        $this->assertSame($z1, $res->json('data.2.source.id'));
    }

    public function test_filter_und_suche_funktionieren_in_der_kundenansicht(): void
    {
        $a = Customer::factory()->create(['company_name' => 'Alpha Tours']);
        $b = Customer::factory()->create(['company_name' => 'Beta Reisen']);
        $this->angebot($a);
        $idB = $this->angebot($b);

        $this->getJson('/api/documents?order=chain&q=Beta')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $idB);
        $this->getJson("/api/documents?order=chain&customer_id={$b->id}&type=angebot")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/documents?order=quatsch')->assertStatus(422);
    }
}
