<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentPdf;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(SettingsSeeder::class);
        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457', 'city' => 'Hamburg',
            'tax_number' => '12/345/67890', 'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX', 'bank_name' => 'Testbank',
            'footer_text' => 'Danke für Ihr Vertrauen.', 'payment_term_days' => 14,
        ]);
        $this->customer = Customer::factory()->create([
            'company_name' => 'Reisebüro Sonnenschein', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München',
        ]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function draft(string $type = 'rechnung', array $override = []): array
    {
        return $this->as(Role::Buchhaltung)->postJson('/api/documents', array_merge([
            'type' => $type, 'customer_id' => $this->customer->id, 'service_date_from' => '2027-05-10',
            'subject' => 'Busfahrt München – Salzburg', 'intro_text' => "Guten Tag,\nwir bieten an:",
            'lines' => [['title' => 'Busfahrt <b>fett</b>', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '1000', 'tax_rate' => 19]],
        ], $override))->assertCreated()->json();
    }

    private function finalize(string $id): array
    {
        return $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize")->assertOk()->json();
    }

    private function html(string $id): string
    {
        return app(DocumentPdf::class)->html(Document::findOrFail($id));
    }

    // ---------- Schnittstelle ----------

    public function test_pdf_ist_ein_echtes_pdf_mit_dem_belegnamen(): void
    {
        $doc = $this->finalize($this->draft()['id']);

        $res = $this->as(Role::Disponent)->get("/api/documents/{$doc['id']}/pdf")->assertOk();

        $res->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString($doc['number'].'.pdf', $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $res->getContent());
        $this->assertGreaterThan(2000, strlen($res->getContent()));
    }

    public function test_pdf_eines_entwurfs_heisst_entwurf(): void
    {
        $doc = $this->draft('angebot');

        $res = $this->as(Role::Admin)->get("/api/documents/{$doc['id']}/pdf")->assertOk();

        $this->assertStringContainsString('Entwurf-angebot.pdf', $res->headers->get('Content-Disposition'));
    }

    public function test_pdf_nur_fuer_berechtigte_und_nur_fuer_vorhandene_belege(): void
    {
        $doc = $this->draft();

        $this->as(Role::Fahrer)->get("/api/documents/{$doc['id']}/pdf")->assertForbidden();
        $this->as(Role::Admin)->get('/api/documents/01a1f9f5-6b2d-772f-8cab-048120e2320b/pdf')->assertNotFound();
    }

    public function test_ohne_anmeldung_kein_pdf(): void
    {
        $doc = $this->draft();
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/documents/{$doc['id']}/pdf")->assertUnauthorized();
    }

    public function test_pdf_abruf_steht_im_aenderungsprotokoll(): void
    {
        $doc = $this->finalize($this->draft()['id']);
        $this->as(Role::Buchhaltung)->get("/api/documents/{$doc['id']}/pdf")->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'pdf_created', 'auditable_id' => $doc['id']]);
        $this->assertSame(1, AuditLog::where('event', 'pdf_created')->count());
    }

    // ---------- Inhalt ----------

    public function test_rechnung_enthaelt_pflichtangaben_und_zahlungshinweis(): void
    {
        $doc = $this->finalize($this->draft()['id']);
        $html = $this->html($doc['id']);

        foreach ([$doc['number'], 'Rechnung', 'Busreisen Muster GmbH', 'Steuernr. 12/345/67890', 'Reisebüro Sonnenschein', 'Hauptstr. 1',
            '80331 München', '10.05.2027', '1.000,00 €', '190,00 €', '1.190,00 €', 'IBAN DE89370400440532013000', 'bis zum',
            'Danke für Ihr Vertrauen.', 'Busfahrt München – Salzburg'] as $expected) {
            $this->assertStringContainsString($expected, $html, "fehlt im PDF: {$expected}");
        }
        $this->assertStringNotContainsString('ENTWURF', $html);
    }

    public function test_entwurf_traegt_den_hinweis_und_keine_nummer(): void
    {
        $html = $this->html($this->draft()['id']);

        $this->assertStringContainsString('ENTWURF', $html);
        $this->assertStringContainsString('wird beim Festschreiben gesetzt', $html);
        $this->assertStringNotContainsString('Rechnungsnr.', $html);
    }

    public function test_texte_werden_maskiert_und_zeilenumbrueche_erhalten(): void
    {
        $html = $this->html($this->draft()['id']);

        $this->assertStringContainsString('Busfahrt &lt;b&gt;fett&lt;/b&gt;', $html);   // kein Schadcode aus Eingaben
        $this->assertStringNotContainsString('<b>fett</b>', $html);
        $this->assertStringContainsString('Guten Tag,<br />', $html);
    }

    public function test_pdf_zeigt_die_kopie_beim_festschreiben_nicht_die_heutigen_stammdaten(): void
    {
        $doc = $this->finalize($this->draft()['id']);
        $this->customer->update(['company_name' => 'Umbenannt AG', 'city' => 'Berlin']);
        CompanySetting::current()->update(['name' => 'Neue Firma GmbH', 'footer_text' => 'Neuer Fußtext']);

        $html = $this->html($doc['id']);

        $this->assertStringContainsString('Reisebüro Sonnenschein', $html);
        $this->assertStringContainsString('80331 München', $html);
        $this->assertStringContainsString('Busreisen Muster GmbH', $html);
        $this->assertStringContainsString('Danke für Ihr Vertrauen.', $html);
        $this->assertStringNotContainsString('Umbenannt AG', $html);
        $this->assertStringNotContainsString('Neuer Fußtext', $html);
    }

    public function test_steuerhinweis_wird_als_fussnote_gedruckt(): void
    {
        $doc = $this->finalize($this->draft('rechnung', ['lines' => [
            ['title' => 'Auslandsfahrt', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => '500', 'tax_rate' => 0, 'tax_note' => 'nicht steuerbar, Leistungsort Ausland'],
        ]])['id']);

        $html = $this->html($doc['id']);

        $this->assertStringContainsString('nicht steuerbar, Leistungsort Ausland', $html);
        $this->assertStringContainsString('Pos. 1', $html);
    }

    public function test_schlussrechnung_zeigt_abzug_und_zahlbetrag(): void
    {
        $angebot = $this->finalize($this->draft('angebot')['id']);
        $anz = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$angebot['id']}/convert", ['type' => 'anzahlung'])->json();
        $anz = $this->finalize($anz['id']);
        $schluss = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$angebot['id']}/convert", ['type' => 'rechnung'])->json();
        $schluss = $this->finalize($schluss['id']);

        $html = $this->html($schluss['id']);

        $this->assertStringContainsString('abzüglich Anzahlung '.$anz['number'], $html);
        $this->assertStringContainsString('Zu zahlen', $html);
        $this->assertStringContainsString('833,00 €', $html);   // 1190 − 357
    }

    public function test_storno_nennt_den_ursprungsbeleg(): void
    {
        $rechnung = $this->finalize($this->draft()['id']);
        $storno = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$rechnung['id']}/cancel")->json();

        $html = $this->html($storno['id']);

        $this->assertStringContainsString('Storno zu', $html);
        $this->assertStringContainsString($rechnung['number'], $html);
        $this->assertStringContainsString('-1.190,00 €', $html);
    }

    public function test_angebot_hat_keinen_zahlungshinweis(): void
    {
        $html = $this->html($this->finalize($this->draft('angebot')['id'])['id']);

        $this->assertStringNotContainsString('Bitte überweisen Sie', $html);
        $this->assertStringNotContainsString('Fällig am', $html);
    }
}
