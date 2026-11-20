<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Anzahlungsrechnung und Schlussrechnung mit Abzug der Anzahlungen. */
class DocumentAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457',
            'city' => 'Hamburg', 'tax_number' => '12/345/67890', 'payment_term_days' => 14,
        ]);
        $this->customer = Customer::factory()->create([
            'company_name' => 'Reisebüro Sonnenschein', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München',
        ]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    /** Festgeschriebenes Angebot über 1000 € netto (19 %). */
    private function angebot(?array $lines = null): array
    {
        $id = $this->as(Role::Buchhaltung)->postJson('/api/documents', [
            'type' => 'angebot', 'customer_id' => $this->customer->id, 'service_date_from' => '2027-05-10',
            'lines' => $lines ?? [['title' => 'Busfahrt', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '1000', 'tax_rate' => 19]],
        ])->assertCreated()->json('id');

        return $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertOk()->json();
    }

    private function convert(string $id, string $type, ?float $percent = null): array
    {
        return $this->as(Role::Buchhaltung)
            ->postJson("/api/documents/{$id}/convert", array_filter(['type' => $type, 'percent' => $percent]))
            ->assertCreated()->json();
    }

    private function finalize(string $id): array
    {
        return $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertOk()->json();
    }

    // ---------- Anzahlungsrechnung ----------

    public function test_anzahlung_hat_standardmaessig_30_prozent_des_auftrags(): void
    {
        $angebot = $this->angebot();

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$angebot['id']}/convert", ['type' => 'anzahlung'])
            ->assertCreated()
            ->assertJsonCount(1, 'lines')
            ->assertJsonPath('lines.0.unit_price', '300.00')
            ->assertJsonPath('lines.0.tax_rate', '19.00')
            ->assertJsonPath('net_total', '300.00')
            ->assertJsonPath('gross_total', '357.00');
    }

    public function test_anzahlung_mit_eigenem_prozentsatz_und_mehreren_steuersaetzen(): void
    {
        $angebot = $this->angebot([
            ['title' => 'Fahrt', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '1000', 'tax_rate' => 19],
            ['title' => 'Transfer', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '200', 'tax_rate' => 7],
        ]);

        $doc = $this->convert($angebot['id'], 'anzahlung', 50);

        $this->assertCount(2, $doc['lines']);        // je Steuersatz eine Position
        $this->assertSame('500.00', $doc['lines'][0]['unit_price']);
        $this->assertSame('100.00', $doc['lines'][1]['unit_price']);
        $this->assertSame('600.00', $doc['net_total']);
    }

    public function test_ungueltiger_prozentsatz_wird_abgelehnt(): void
    {
        $angebot = $this->angebot();

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$angebot['id']}/convert", ['type' => 'anzahlung', 'percent' => 0])
            ->assertUnprocessable();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$angebot['id']}/convert", ['type' => 'anzahlung', 'percent' => 150])
            ->assertUnprocessable();
    }

    // ---------- Schlussrechnung ----------

    public function test_schlussrechnung_zieht_festgeschriebene_anzahlung_getrennt_ab(): void
    {
        $angebot = $this->angebot();
        $anzahlung = $this->finalize($this->convert($angebot['id'], 'anzahlung')['id']);

        $schluss = $this->convert($angebot['id'], 'rechnung');

        $this->assertSame('1000.00', $schluss['net_total']);         // volle Leistung, nicht gekürzt
        $this->assertSame('1190.00', $schluss['gross_total']);
        $this->assertCount(1, $schluss['deductions']);
        $this->assertSame($anzahlung['id'], $schluss['deductions'][0]['document_id']);
        $this->assertSame($anzahlung['number'], $schluss['deductions'][0]['number']);
        $this->assertSame('300.00', $schluss['deduction_net']);
        $this->assertSame('57.00', $schluss['deduction_tax']);
        $this->assertSame('357.00', $schluss['deduction_gross']);
        $this->assertSame('833.00', $schluss['payable_total']);      // 1190 − 357
    }

    public function test_nicht_festgeschriebene_anzahlung_wird_nicht_abgezogen(): void
    {
        $angebot = $this->angebot();
        $this->convert($angebot['id'], 'anzahlung');                  // bleibt Entwurf

        $schluss = $this->convert($angebot['id'], 'rechnung');

        $this->assertSame([], $schluss['deductions']);
        $this->assertSame('1190.00', $schluss['payable_total']);
    }

    public function test_anzahlung_nach_dem_anlegen_der_schlussrechnung_zaehlt_beim_festschreiben(): void
    {
        $angebot = $this->angebot();
        $schluss = $this->convert($angebot['id'], 'rechnung');        // Entwurf zuerst
        $this->finalize($this->convert($angebot['id'], 'anzahlung')['id']);

        $fest = $this->finalize($schluss['id']);

        $this->assertSame('357.00', $fest['deduction_gross']);
        $this->assertSame('833.00', $fest['payable_total']);
    }

    public function test_stornierte_anzahlung_wird_nicht_abgezogen(): void
    {
        $angebot = $this->angebot();
        $anzahlung = $this->finalize($this->convert($angebot['id'], 'anzahlung')['id']);
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$anzahlung['id']}/cancel")->assertCreated();

        $schluss = $this->convert($angebot['id'], 'rechnung');

        $this->assertSame([], $schluss['deductions']);
    }

    public function test_mehrere_anzahlungen_werden_summiert(): void
    {
        $angebot = $this->angebot();
        $this->finalize($this->convert($angebot['id'], 'anzahlung', 30)['id']);
        $this->finalize($this->convert($angebot['id'], 'anzahlung', 20)['id']);

        $schluss = $this->convert($angebot['id'], 'rechnung');

        $this->assertCount(2, $schluss['deductions']);
        $this->assertSame('500.00', $schluss['deduction_net']);       // 300 + 200
        $this->assertSame('595.00', $schluss['deduction_gross']);
        $this->assertSame('595.00', $schluss['payable_total']);
    }

    public function test_eine_anzahlung_wird_nur_von_einer_gueltigen_schlussrechnung_abgezogen(): void
    {
        $angebot = $this->angebot();
        $this->finalize($this->convert($angebot['id'], 'anzahlung')['id']);
        $erste = $this->finalize($this->convert($angebot['id'], 'rechnung')['id']);

        // Zweite Schlussrechnung aus demselben Angebot: Die Anzahlung ist schon vergeben.
        $zweite = $this->convert($angebot['id'], 'rechnung');
        $this->assertSame([], $zweite['deductions']);

        // Nach dem Storno der ersten ist sie wieder frei.
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$erste['id']}/cancel")->assertCreated();
        $dritte = $this->convert($angebot['id'], 'rechnung');
        $this->assertCount(1, $dritte['deductions']);
    }

    public function test_storno_der_schlussrechnung_spiegelt_den_abzug(): void
    {
        $angebot = $this->angebot();
        $this->finalize($this->convert($angebot['id'], 'anzahlung')['id']);
        $schluss = $this->finalize($this->convert($angebot['id'], 'rechnung')['id']);

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$schluss['id']}/cancel")
            ->assertCreated()
            ->assertJsonPath('gross_total', '-1190.00')
            ->assertJsonPath('deduction_gross', '-357.00')
            ->assertJsonPath('payable_total', '-833.00');
    }

    public function test_anzahlungen_ueber_dem_rechnungsbetrag_verhindern_das_festschreiben(): void
    {
        $angebot = $this->angebot();
        $this->finalize($this->convert($angebot['id'], 'anzahlung', 100)['id']);
        $schluss = $this->convert($angebot['id'], 'rechnung');

        // Die Leistung wird nachträglich gekürzt: Die Anzahlung (1190 €) übersteigt jetzt den Rechnungsbetrag.
        $this->as(Role::Buchhaltung)->putJson("/api/documents/{$schluss['id']}", [
            'customer_id' => $this->customer->id, 'service_date_from' => '2027-05-10',
            'lines' => [['title' => 'Busfahrt', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '500', 'tax_rate' => 19]],
        ])->assertOk();

        $res = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$schluss['id']}/finalize")->assertUnprocessable();
        $this->assertStringContainsString('übersteigen', $res->json('errors.document.0'));
    }

    public function test_schlussrechnung_ohne_anzahlung_zahlt_den_vollen_betrag(): void
    {
        $angebot = $this->angebot();

        $fest = $this->finalize($this->convert($angebot['id'], 'rechnung')['id']);

        $this->assertSame('1190.00', $fest['gross_total']);
        $this->assertSame('1190.00', $fest['payable_total']);
        $this->assertSame('0.00', $fest['deduction_gross']);
    }

    public function test_normale_belege_haben_den_zahlbetrag_gleich_brutto(): void
    {
        $angebot = $this->angebot();

        $this->assertSame('1190.00', $angebot['payable_total']);
    }
}
