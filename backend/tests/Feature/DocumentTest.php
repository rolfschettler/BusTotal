<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(SettingsSeeder::class);

        // Vollständige Firmendaten: Ohne Steuernummer lässt sich keine Rechnung festschreiben.
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

    private function line(array $override = []): array
    {
        return array_merge([
            'title' => 'Busfahrt München – Salzburg', 'quantity' => '1', 'unit' => 'Fahrt',
            'unit_price' => '1000.00', 'tax_rate' => '19',
        ], $override);
    }

    private function payload(string $type, array $override = []): array
    {
        return array_merge([
            'type' => $type, 'customer_id' => $this->customer->id,
            'service_date_from' => '2027-05-10', 'lines' => [$this->line()],
        ], $override);
    }

    /** Entwurf über die Schnittstelle anlegen und die ID zurückgeben. */
    private function draft(string $type = 'rechnung', array $override = [], Role $role = Role::Buchhaltung): string
    {
        return $this->as($role)->postJson('/api/documents', $this->payload($type, $override))->assertCreated()->json('id');
    }

    private function finalized(string $type = 'rechnung', array $override = []): array
    {
        $id = $this->draft($type, $override);

        return $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertOk()->json();
    }

    // ---------- Zugriff ----------

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->getJson('/api/documents')->assertUnauthorized();
    }

    public function test_fahrer_hat_keinen_zugriff(): void
    {
        $this->as(Role::Fahrer)->getJson('/api/documents')->assertForbidden();
        $this->as(Role::Fahrer)->postJson('/api/documents', $this->payload('angebot'))->assertForbidden();
    }

    public function test_disponent_darf_entwurf_anlegen_aber_nicht_festschreiben(): void
    {
        $id = $this->draft('angebot', [], Role::Disponent);

        $this->as(Role::Disponent)->postJson("/api/documents/{$id}/finalize")->assertForbidden();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertOk();
    }

    // ---------- Entwurf und Rechnen ----------

    public function test_entwurf_hat_keine_nummer_und_summen_werden_berechnet(): void
    {
        $this->as(Role::Buchhaltung)->postJson('/api/documents', $this->payload('rechnung', ['lines' => [
            $this->line(['unit_price' => '1000.00', 'tax_rate' => '19']),
            $this->line(['title' => 'Transfer', 'unit_price' => '200.00', 'tax_rate' => '7']),
        ]]))
            ->assertCreated()
            ->assertJsonPath('status', 'entwurf')
            ->assertJsonPath('number', null)
            ->assertJsonPath('net_total', '1200.00')
            ->assertJsonPath('tax_total', '204.00')   // 190,00 + 14,00
            ->assertJsonPath('gross_total', '1404.00')
            ->assertJsonCount(2, 'tax_summary')
            ->assertJsonPath('tax_summary.0.rate', '19.00');
    }

    public function test_steuer_wird_je_steuersatz_aus_der_nettosumme_gerechnet(): void
    {
        // Drei Positionen à 0,10 € bei 19 %: je Position 0,019 → 0,02, zusammen 0,06.
        // Aus der Summe (0,30 €) ergibt sich 0,057 → 0,06. Bei 3 × 0,15 € (0,45 €) wären es 0,0855 → 0,09,
        // je Position dagegen 3 × 0,03 = 0,09. Wir rechnen je Satz aus der Summe.
        $this->as(Role::Buchhaltung)->postJson('/api/documents', $this->payload('rechnung', ['lines' => [
            $this->line(['unit_price' => '0.10']), $this->line(['unit_price' => '0.10']), $this->line(['unit_price' => '0.10']),
        ]]))->assertCreated()
            ->assertJsonPath('net_total', '0.30')
            ->assertJsonPath('tax_total', '0.06');
    }

    public function test_positionsbetrag_wird_kaufmaennisch_gerundet(): void
    {
        $this->as(Role::Buchhaltung)->postJson('/api/documents', $this->payload('angebot', ['lines' => [
            $this->line(['quantity' => '0.5', 'unit_price' => '0.05', 'tax_rate' => '19']),  // 0,025 → 0,03
            $this->line(['quantity' => '3', 'unit_price' => '33.33', 'tax_rate' => '19']),   // 99,99
        ]]))->assertCreated()
            ->assertJsonPath('lines.0.net_amount', '0.03')
            ->assertJsonPath('lines.1.net_amount', '99.99')
            ->assertJsonPath('net_total', '100.02');
    }

    public function test_entwurf_laesst_sich_aendern_und_loeschen(): void
    {
        $id = $this->draft('angebot');

        $this->as(Role::Disponent)->putJson("/api/documents/{$id}", [
            'subject' => 'Neuer Betreff', 'lines' => [$this->line(['unit_price' => '500'])],
        ])->assertOk()->assertJsonPath('net_total', '500.00')->assertJsonCount(1, 'lines');

        $this->as(Role::Disponent)->deleteJson("/api/documents/{$id}")->assertOk();
        $this->assertDatabaseMissing('documents', ['id' => $id]);
    }

    public function test_belegart_kann_nicht_geaendert_werden(): void
    {
        $id = $this->draft('angebot');

        $this->as(Role::Admin)->putJson("/api/documents/{$id}", ['type' => 'rechnung', 'lines' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_ungueltige_eingaben_werden_abgelehnt(): void
    {
        $this->as(Role::Admin)->postJson('/api/documents', $this->payload('rechnung', ['lines' => [
            $this->line(['quantity' => '0', 'tax_rate' => '150', 'title' => '']),
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity', 'lines.0.tax_rate', 'lines.0.title']);
    }

    // ---------- Festschreiben ----------

    public function test_festschreiben_vergibt_nummer_und_kopiert_stammdaten(): void
    {
        $doc = $this->finalized('rechnung');

        $this->assertSame('festgeschrieben', $doc['status']);
        $this->assertSame('RE-'.now()->format('Y').'-00001', $doc['number']);
        $this->assertSame('Busreisen Muster GmbH', $doc['seller']['name']);
        $this->assertSame('Reisebüro Sonnenschein', $doc['recipient']['name']);
        $this->assertSame('80331', $doc['recipient']['postal_code']);
        $this->assertSame(now()->addDays(14)->toDateString(), $doc['due_date']);
        $this->assertNotNull($doc['finalized_at']);
    }

    public function test_pflichtangaben_werden_vor_dem_festschreiben_geprueft(): void
    {
        CompanySetting::current()->update(['tax_number' => null, 'vat_id' => null]);
        $this->customer->update(['street' => null]);
        $id = $this->draft('rechnung', ['service_date_from' => null, 'lines' => [$this->line(['tax_rate' => '0'])]]);

        $res = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertUnprocessable();
        $text = implode(' | ', $res->json('errors.document'));

        $this->assertStringContainsString('Steuernummer', $text);
        $this->assertStringContainsString('Anschrift', $text);
        $this->assertStringContainsString('Leistungszeitpunkt', $text);
        $this->assertStringContainsString('0 % Steuer', $text);
        $this->assertSame('entwurf', Document::find($id)->status->value);
    }

    public function test_beleg_ohne_positionen_laesst_sich_nicht_festschreiben(): void
    {
        $id = $this->draft('angebot', ['lines' => []]);

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertUnprocessable();
    }

    public function test_angebot_braucht_keine_steuernummer(): void
    {
        CompanySetting::current()->update(['tax_number' => null, 'vat_id' => null]);

        $this->finalized('angebot', ['service_date_from' => null]);
        $this->addToAssertionCount(1);
    }

    public function test_fehlgeschlagenes_festschreiben_verbraucht_keine_nummer(): void
    {
        $bad = $this->draft('rechnung', ['service_date_from' => null]);
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$bad}/finalize")->assertUnprocessable();

        $this->assertStringEndsWith('-00001', $this->finalized('rechnung')['number']);
    }

    public function test_geloeschter_entwurf_verbraucht_keine_nummer(): void
    {
        $id = $this->draft('rechnung');
        $this->as(Role::Buchhaltung)->deleteJson("/api/documents/{$id}")->assertOk();

        $this->assertStringEndsWith('-00001', $this->finalized('rechnung')['number']);
    }

    public function test_nummern_sind_fortlaufend_und_anzahlung_teilt_den_rechnungskreis(): void
    {
        $this->assertStringEndsWith('-00001', $this->finalized('anzahlung')['number']);
        $this->assertStringEndsWith('-00002', $this->finalized('rechnung')['number']);
        $this->assertStringStartsWith('AN-', $this->finalized('angebot')['number']);
    }

    public function test_doppeltes_festschreiben_wird_abgelehnt(): void
    {
        $doc = $this->finalized('rechnung');

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$doc['id']}/finalize")->assertUnprocessable();
    }

    // ---------- Unveränderbarkeit (GoBD) ----------

    public function test_festgeschriebener_beleg_ist_ueber_die_schnittstelle_gesperrt(): void
    {
        $doc = $this->finalized('rechnung');

        $this->as(Role::Admin)->putJson("/api/documents/{$doc['id']}", ['subject' => 'x', 'lines' => [$this->line()]])
            ->assertUnprocessable();
        $this->as(Role::Admin)->deleteJson("/api/documents/{$doc['id']}")->assertUnprocessable();
    }

    public function test_festgeschriebener_beleg_ist_auch_im_modell_gesperrt(): void
    {
        $doc = $this->finalized('rechnung');

        $model = Document::findOrFail($doc['id']);
        $model->subject = 'Heimlich geändert';
        try {
            $model->save();
            $this->fail('Änderung hätte abgelehnt werden müssen.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(LogicException::class);
        $model->fresh()->delete();
    }

    public function test_positionen_eines_festgeschriebenen_belegs_sind_gesperrt(): void
    {
        $doc = $this->finalized('rechnung');
        $line = DocumentLine::where('document_id', $doc['id'])->firstOrFail();

        $this->expectException(LogicException::class);
        $line->update(['unit_price' => '1.00']);
    }

    public function test_beleg_behaelt_die_kundendaten_von_damals(): void
    {
        $doc = $this->finalized('rechnung');
        $this->customer->update(['company_name' => 'Umbenannt AG', 'city' => 'Berlin']);

        $this->as(Role::Admin)->getJson("/api/documents/{$doc['id']}")
            ->assertOk()
            ->assertJsonPath('recipient.name', 'Reisebüro Sonnenschein')
            ->assertJsonPath('recipient.city', 'München');
    }

    // ---------- Storno ----------

    public function test_storno_erzeugt_gutschrift_mit_negativen_betraegen(): void
    {
        $doc = $this->finalized('rechnung');

        $storno = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$doc['id']}/cancel")
            ->assertCreated()
            ->assertJsonPath('type', 'gutschrift')
            ->assertJsonPath('status', 'festgeschrieben')
            ->assertJsonPath('cancels_id', $doc['id'])
            ->assertJsonPath('net_total', '-1000.00')
            ->assertJsonPath('gross_total', '-1190.00')
            ->assertJsonPath('recipient.name', 'Reisebüro Sonnenschein')
            ->json();

        $this->assertStringStartsWith('GS-', $storno['number']);

        // Das Original bleibt unverändert und gültig im Bestand, trägt nur den Verweis auf den Storno.
        $original = Document::findOrFail($doc['id']);
        $this->assertSame($storno['id'], $original->cancelled_by_id);
        $this->assertSame($doc['number'], $original->number);
        $this->assertSame('1000.00', (string) $original->net_total);
    }

    public function test_beleg_kann_nur_einmal_storniert_werden(): void
    {
        $doc = $this->finalized('rechnung');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$doc['id']}/cancel")->assertCreated();

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$doc['id']}/cancel")->assertUnprocessable();
    }

    public function test_nicht_stornierbar_sind_entwurf_angebot_und_gutschrift(): void
    {
        $draft = $this->draft('rechnung');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$draft}/cancel")->assertUnprocessable();

        $angebot = $this->finalized('angebot');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$angebot['id']}/cancel")->assertUnprocessable();

        $rechnung = $this->finalized('rechnung');
        $storno = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$rechnung['id']}/cancel")->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$storno['id']}/cancel")->assertUnprocessable();
    }

    public function test_disponent_darf_nicht_stornieren(): void
    {
        $doc = $this->finalized('rechnung');

        $this->as(Role::Disponent)->postJson("/api/documents/{$doc['id']}/cancel")->assertForbidden();
    }

    // ---------- Belegkette ----------

    public function test_aus_angebot_wird_auftragsbestaetigung_und_daraus_die_anzahlung(): void
    {
        $angebot = $this->finalized('angebot');

        $ab = $this->as(Role::Disponent)->postJson("/api/documents/{$angebot['id']}/convert", ['type' => 'auftragsbestaetigung'])
            ->assertCreated()
            ->assertJsonPath('status', 'entwurf')
            ->assertJsonPath('source_id', $angebot['id'])
            ->assertJsonPath('customer_id', $this->customer->id)
            ->assertJsonPath('net_total', '1000.00')
            ->assertJsonCount(1, 'lines')
            ->json();

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$ab['id']}/finalize")->assertOk();
        $this->as(Role::Disponent)->postJson("/api/documents/{$ab['id']}/convert", ['type' => 'anzahlung'])
            ->assertCreated()->assertJsonPath('type', 'anzahlung');
    }

    public function test_die_rechnung_entsteht_nicht_aus_der_belegkette(): void
    {
        // Konzept (docs/fachlich/workflow-reisevorhaben.md): Die Rechnung kommt aus den Fahrtaufträgen (Etappe 4).
        $angebot = $this->finalized('angebot');
        $this->as(Role::Disponent)->postJson("/api/documents/{$angebot['id']}/convert", ['type' => 'rechnung'])->assertUnprocessable();
        $this->as(Role::Disponent)->postJson("/api/documents/{$angebot['id']}/convert", ['type' => 'anzahlung'])->assertUnprocessable();

        $ab = $this->as(Role::Disponent)->postJson("/api/documents/{$angebot['id']}/convert", ['type' => 'auftragsbestaetigung'])->json();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$ab['id']}/finalize")->assertOk();
        $this->as(Role::Disponent)->postJson("/api/documents/{$ab['id']}/convert", ['type' => 'rechnung'])->assertUnprocessable();

        $this->assertSame([DocumentType::Auftragsbestaetigung], DocumentType::Angebot->convertibleTo());
        $this->assertSame([DocumentType::Anzahlung], DocumentType::Auftragsbestaetigung->convertibleTo());
        $this->assertSame([], DocumentType::Rechnung->convertibleTo());
    }

    public function test_aus_einem_angebot_gibt_es_nur_eine_gueltige_auftragsbestaetigung(): void
    {
        $angebot = $this->finalized('angebot');
        $url = "/api/documents/{$angebot['id']}/convert";

        // Das Angebot zeigt, dass es schon eine Auftragsbestätigung gibt (die Oberfläche zeigt dann einen Verweis statt der Taste)
        $this->as(Role::Disponent)->getJson("/api/documents/{$angebot['id']}")->assertJsonPath('existing_followups', []);

        $ab = $this->as(Role::Disponent)->postJson($url, ['type' => 'auftragsbestaetigung'])->assertCreated()->json();
        $this->as(Role::Disponent)->getJson("/api/documents/{$angebot['id']}")
            ->assertJsonPath('existing_followups.auftragsbestaetigung.id', $ab['id']);

        // Auch ein Entwurf zählt: Die zweite Auftragsbestätigung wird abgelehnt und nennt die vorhandene
        $this->as(Role::Disponent)->postJson($url, ['type' => 'auftragsbestaetigung'])
            ->assertUnprocessable()->assertJsonValidationErrors(['document']);
        $this->assertSame(1, Document::where('source_id', $angebot['id'])->count());

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$ab['id']}/finalize")->assertOk();
        $res = $this->as(Role::Disponent)->postJson($url, ['type' => 'auftragsbestaetigung'])->assertUnprocessable();
        $this->assertStringContainsString(Document::find($ab['id'])->number, $res->json('errors.document.0'));

        // Anzahlungen gibt es zur Auftragsbestätigung, nicht zum Angebot; mehrere sind möglich (Teilzahlungen)
        $abUrl = "/api/documents/{$ab['id']}/convert";
        $this->as(Role::Buchhaltung)->postJson($abUrl, ['type' => 'anzahlung'])->assertCreated();
        $this->as(Role::Buchhaltung)->postJson($abUrl, ['type' => 'anzahlung'])->assertCreated();
    }

    public function test_folgebeleg_nur_aus_festgeschriebenem_und_erlaubtem_beleg(): void
    {
        $draft = $this->draft('angebot');
        $this->as(Role::Disponent)->postJson("/api/documents/{$draft}/convert", ['type' => 'rechnung'])->assertUnprocessable();

        $rechnung = $this->finalized('rechnung');
        $this->as(Role::Disponent)->postJson("/api/documents/{$rechnung['id']}/convert", ['type' => 'angebot'])->assertUnprocessable();
    }

    // ---------- Liste ----------

    public function test_liste_filtert_nach_art_status_und_sucht_nach_nummer_und_kunde(): void
    {
        $this->finalized('rechnung');
        $this->draft('angebot');

        $this->as(Role::Admin)->getJson('/api/documents')->assertOk()->assertJsonCount(2, 'data');
        $this->as(Role::Admin)->getJson('/api/documents?type=rechnung')->assertJsonCount(1, 'data');
        $this->as(Role::Admin)->getJson('/api/documents?status=entwurf')->assertJsonCount(1, 'data');
        $this->as(Role::Admin)->getJson('/api/documents?q=Sonnenschein')->assertJsonCount(2, 'data');
        $this->as(Role::Admin)->getJson('/api/documents?q=RE-'.now()->format('Y'))->assertJsonCount(1, 'data');
        $this->as(Role::Admin)->getJson('/api/documents?q=gibtesnicht')->assertJsonCount(0, 'data');
    }

    public function test_belegarten_liste_fuer_formulare(): void
    {
        $this->as(Role::Disponent)->getJson('/api/documents/types')->assertOk()->assertJsonCount(5)
            ->assertJsonPath('0.value', 'angebot');
    }
}
