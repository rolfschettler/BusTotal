<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentPayment;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInvoiceDrafts;
use Tests\TestCase;

/**
 * Etappe 0.2: Zahlungseingang und Rückzahlung zu Rechnungen und Anzahlungsrechnungen.
 * Der Beleg bleibt unverändert, der Zahlstand ergibt sich aus den Buchungen (docs/fachlich/loesungskonzept-abrechnung.md 3.1).
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInvoiceDrafts;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
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

    /** Festgeschriebene Rechnung über 1.000 € netto = 1.190 € brutto. */
    private function invoice(string $type = 'rechnung'): string
    {
        $id = $this->as(Role::Buchhaltung)->postJson('/api/documents', [
            'type' => $type, 'customer_id' => $this->customer->id, 'service_date_from' => '2027-05-10',
            'lines' => [['title' => 'Busfahrt', 'quantity' => '1', 'unit' => 'Fahrt', 'unit_price' => '1000.00', 'tax_rate' => '19']],
        ])->assertCreated()->json('id');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertOk();

        return $id;
    }

    private function pay(string $id, array $body = [], Role $role = Role::Buchhaltung)
    {
        return $this->as($role)->postJson("/api/documents/{$id}/payments", $body + [
            'paid_on' => Carbon::today()->toDateString(), 'amount' => '1190.00', 'method' => 'ueberweisung',
        ]);
    }

    // ---------- Buchen ----------

    public function test_zahlung_buchen_setzt_den_zahlstand(): void
    {
        $id = $this->invoice();

        $this->pay($id, ['amount' => '400.00', 'reference' => 'RE-1 Anzahlung'])->assertCreated()
            ->assertJsonPath('summary.payable', '1190.00')
            ->assertJsonPath('summary.paid', '400.00')
            ->assertJsonPath('summary.open', '790.00')
            ->assertJsonPath('summary.state', 'teilweise')
            ->assertJsonPath('payments.0.reference', 'RE-1 Anzahlung');

        $this->pay($id, ['amount' => '790.00'])->assertCreated()
            ->assertJsonPath('summary.open', '0.00')
            ->assertJsonPath('summary.state', 'bezahlt');
    }

    public function test_unbezahlt_ist_offen_und_ueberfaellig_nach_der_faelligkeit(): void
    {
        $id = $this->invoice();

        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$id}/payments")->assertOk()
            ->assertJsonPath('summary.state', 'offen')->assertJsonPath('summary.overdue', false);

        // Fälligkeit liegt vor heute: überfällig, solange etwas offen ist
        Document::whereKey($id)->update(['due_date' => Carbon::yesterday()->toDateString()]);
        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$id}/payments")->assertJsonPath('summary.overdue', true);

        $this->pay($id)->assertCreated()->assertJsonPath('summary.overdue', false)->assertJsonPath('summary.state', 'bezahlt');
    }

    public function test_betrag_ueber_dem_offenen_betrag_wird_abgelehnt(): void
    {
        $id = $this->invoice();

        $this->pay($id, ['amount' => '1190.01'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->pay($id)->assertCreated();
        $this->pay($id, ['amount' => '1.00'])->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'Dieser Beleg ist bereits vollständig bezahlt.');
    }

    public function test_betrag_und_datum_werden_geprueft(): void
    {
        $id = $this->invoice();

        $this->pay($id, ['amount' => '0'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->pay($id, ['amount' => '-5'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->pay($id, ['paid_on' => Carbon::tomorrow()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('paid_on');
        $this->pay($id, ['paid_on' => ''])->assertUnprocessable()->assertJsonValidationErrors('paid_on');
        $this->pay($id, ['method' => 'bitcoin'])->assertUnprocessable()->assertJsonValidationErrors('method');
    }

    public function test_nur_rechnung_und_anzahlung_haben_zahlungen(): void
    {
        $angebot = $this->invoice('angebot');
        $this->pay($angebot)->assertUnprocessable();
        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$angebot}")->assertJsonPath('payment', null);

        // Entwurf: keine Zahlung
        $draft = $this->as(Role::Buchhaltung)->postJson('/api/documents', [
            'type' => 'rechnung', 'customer_id' => $this->customer->id,
            'lines' => [['title' => 'X', 'quantity' => '1', 'unit' => 'Stk', 'unit_price' => '10', 'tax_rate' => '19']],
        ])->json('id');
        $this->pay($draft)->assertUnprocessable();

        // Anzahlungsrechnung: ja
        $this->pay($this->invoice('anzahlung'))->assertCreated();
    }

    // ---------- Rückzahlung ----------

    public function test_rueckzahlung_mindert_den_bezahlten_betrag(): void
    {
        $id = $this->invoice();
        $this->pay($id)->assertCreated();

        $this->pay($id, ['kind' => 'rueckzahlung', 'amount' => '200.00', 'note' => 'Kulanz'])->assertCreated()
            ->assertJsonPath('summary.paid', '990.00')
            ->assertJsonPath('summary.open', '200.00')
            ->assertJsonPath('summary.state', 'teilweise');
    }

    public function test_rueckzahlung_nicht_hoeher_als_bezahlt(): void
    {
        $id = $this->invoice();
        $this->pay($id, ['amount' => '100.00'])->assertCreated();

        $this->pay($id, ['kind' => 'rueckzahlung', 'amount' => '100.01'])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_storniertem_beleg_nur_noch_rueckzahlung(): void
    {
        $id = $this->invoice();
        $this->pay($id)->assertCreated();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/cancel")->assertCreated();

        $this->pay($id, ['amount' => '10.00'])->assertUnprocessable();
        $this->pay($id, ['kind' => 'rueckzahlung', 'amount' => '1190.00'])->assertCreated()
            ->assertJsonPath('summary.state', 'storniert')->assertJsonPath('summary.open', '0.00');
    }

    // ---------- Stornieren (nie löschen) ----------

    public function test_buchung_wird_storniert_und_zaehlt_nicht_mehr(): void
    {
        $id = $this->invoice();
        $paymentId = $this->pay($id, ['amount' => '500.00'])->json('payments.0.id');

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/payments/{$paymentId}/cancel", ['reason' => 'Falscher Beleg'])->assertOk()
            ->assertJsonPath('summary.paid', '0.00')
            ->assertJsonPath('summary.state', 'offen')
            ->assertJsonPath('payments.0.cancel_reason', 'Falscher Beleg');

        // die Buchung bleibt erhalten (GoBD)
        $this->assertDatabaseHas('document_payments', ['id' => $paymentId]);
        $this->assertNotNull(DocumentPayment::find($paymentId)->cancelled_at);
    }

    public function test_stornieren_braucht_einen_grund_und_geht_nur_einmal(): void
    {
        $id = $this->invoice();
        $paymentId = $this->pay($id)->json('payments.0.id');

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/payments/{$paymentId}/cancel", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/payments/{$paymentId}/cancel", ['reason' => 'Irrtum'])->assertOk();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/payments/{$paymentId}/cancel", ['reason' => 'nochmal'])->assertUnprocessable();
    }

    public function test_buchung_eines_anderen_belegs_ist_nicht_erreichbar(): void
    {
        $a = $this->invoice();
        $b = $this->invoice();
        $paymentOfA = $this->pay($a)->json('payments.0.id');

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$b}/payments/{$paymentOfA}/cancel", ['reason' => 'x'])->assertNotFound();
    }

    // ---------- Rechte ----------

    public function test_rechte(): void
    {
        $id = $this->invoice();

        $this->pay($id, [], Role::Disponent)->assertForbidden();
        $this->pay($id, [], Role::Fahrer)->assertForbidden();
        $this->as(Role::Disponent)->getJson("/api/documents/{$id}/payments")->assertOk();
        $this->as(Role::Fahrer)->getJson("/api/documents/{$id}/payments")->assertForbidden();
        $this->pay($id, [], Role::Admin)->assertCreated();
    }

    // ---------- Anzeige ----------

    public function test_zahlstand_steht_am_beleg_und_in_der_liste(): void
    {
        $paid = $this->invoice();
        $open = $this->invoice();
        $this->pay($paid)->assertCreated();

        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$paid}")->assertJsonPath('payment.state', 'bezahlt');

        $rows = collect($this->as(Role::Buchhaltung)->getJson('/api/documents')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame('bezahlt', $rows[$paid]['payment']['state']);
        $this->assertSame('offen', $rows[$open]['payment']['state']);
    }

    public function test_jede_antwort_mit_einem_beleg_traegt_den_zahlstand(): void
    {
        // Fehler beim Testen: Nach dem Festschreiben fehlte `payment` in der Antwort, der Abschnitt „Zahlungen“ erschien erst nach F5.
        $id = $this->as(Role::Buchhaltung)->postJson('/api/documents', [
            'type' => 'rechnung', 'customer_id' => $this->customer->id, 'service_date_from' => '2027-05-10',
            'lines' => [['title' => 'Busfahrt', 'quantity' => '1', 'unit' => 'Fahrt', 'unit_price' => '1000.00', 'tax_rate' => '19']],
        ])->assertCreated()->assertJsonPath('payment', null)->json('id');   // Entwurf: noch keine Zahlungen

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertOk()
            ->assertJsonPath('payment.state', 'offen')
            ->assertJsonPath('payment.payable', '1190.00');

        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/cancel")->assertCreated()->assertJsonPath('payment', null);   // Stornorechnung: keine Zahlungen
        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$id}")->assertJsonPath('payment.state', 'storniert');
    }

    public function test_beleg_bleibt_durch_zahlungen_unveraendert(): void
    {
        $id = $this->invoice();
        $before = Document::find($id)->only(['net_total', 'gross_total', 'payable_total', 'status', 'archive_sha256', 'updated_at']);

        $this->pay($id)->assertCreated();

        $after = Document::find($id)->only(['net_total', 'gross_total', 'payable_total', 'status', 'archive_sha256', 'updated_at']);
        $this->assertEquals($before, $after);
    }

    public function test_schlussrechnung_zahlstand_nach_abzug_der_anzahlung(): void
    {
        // Angebot 1.190 €, Anzahlung 30 % = 357 €, Schlussrechnung zu zahlen 833 €
        $offer = $this->invoice('angebot');
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$offer}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json('id');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order}/finalize")->assertOk();
        $advance = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order}/convert", ['type' => 'anzahlung'])->assertCreated()->json('id');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$advance}/finalize")->assertOk();
        $final = $this->invoiceDraftFrom($order)['id'];
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$final}/finalize")->assertOk();

        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$final}/payments")->assertJsonPath('summary.payable', '833.00');
        $this->pay($final, ['amount' => '833.00'])->assertCreated()->assertJsonPath('summary.state', 'bezahlt');
        $this->pay($advance, ['amount' => '357.00'])->assertCreated()->assertJsonPath('summary.state', 'bezahlt');
    }
}
