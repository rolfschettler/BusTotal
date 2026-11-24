<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use App\Models\PricingSetting;
use App\Models\User;
use App\Services\DocumentPdf;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInvoiceDrafts;
use Tests\TestCase;

/**
 * Prüfstand für den Seitenumbruch der Beleg-PDFs. Erzeugt Belege in vielen Größen und liest den Text jeder Seite zurück (pdftotext,
 * fehlt es auf dem Rechner, werden die Tests übersprungen). Regeln, die für jeden Beleg gelten:
 *  - Jede Seite trägt die Fußzeile und „Seite n von N“ mit der richtigen Gesamtzahl.
 *  - Jede Seite mit Positionen trägt die Tabellenüberschrift (Pos. / Bezeichnung …).
 *  - Die Summen stehen nie allein auf einer Seite, mindestens eine Position steht auf derselben Seite.
 *  - Abschnitte der Fahrtbeschreibung werden nie zerrissen; die Überschrift steht nie als letzte Zeile einer Seite.
 *  - Der Schlusstext (Zahlung, Gruß) steht nie allein auf einer Seite.
 */
class PdfPageBreakTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInvoiceDrafts;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->pdftotext() === null) {
            $this->markTestSkipped('pdftotext ist nicht installiert.');
        }
        Storage::fake('local');
        Http::fake();
        $this->seed(SettingsSeeder::class);
        PricingSetting::current()->update(['price_per_km' => 2, 'empty_km_percent' => 100, 'tax_rate' => 19, 'hourly_rate' => 0, 'day_rate' => 0, 'overnight_rate' => 0, 'minimum_price' => 0]);
        CompanySetting::current()->update([
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457', 'city' => 'Hamburg',
            'tax_number' => '12/345/67890', 'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX', 'bank_name' => 'Testbank',
            'footer_text' => 'Danke für Ihr Vertrauen.', 'payment_term_days' => 14,
        ]);
        $this->customer = Customer::factory()->create(['company_name' => 'Reisebüro Sonnenschein', 'street' => 'Hauptstr. 1', 'postal_code' => '80331', 'city' => 'München']);
    }

    private function pdftotext(): ?string
    {
        foreach (['pdftotext', 'C:/msys64/mingw64/bin/pdftotext.exe'] as $bin) {
            $out = [];
            @exec(escapeshellarg($bin).' -v 2>&1', $out, $code);
            if (str_contains(implode(' ', $out), 'pdftotext')) {
                return $bin;
            }
        }

        return null;
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    /** Text je Seite eines frisch erzeugten PDFs. @return list<string> */
    private function pages(Document $doc): array
    {
        $pdf = tempnam(sys_get_temp_dir(), 'pdfbreak').'.pdf';
        file_put_contents($pdf, app(DocumentPdf::class)->render($doc));
        $text = (string) shell_exec(escapeshellarg($this->pdftotext()).' -enc UTF-8 -layout '.escapeshellarg($pdf).' - 2>&1');
        @unlink($pdf);

        $pages = explode("\f", $text);
        if (trim((string) end($pages)) === '') {
            array_pop($pages);
        }

        return $pages;
    }

    /** Rechnung mit $lines Positionen, jede mit Beschreibung von $descLines Zeilen. */
    private function invoice(int $lines, int $descLines = 1, array $over = [], int $taxNotes = 0, string $type = 'rechnung'): Document
    {
        $body = [];
        for ($i = 1; $i <= $lines; $i++) {
            $body[] = [
                'title' => sprintf('Zeile %02d', $i), 'description' => implode("\n", array_fill(0, $descLines, 'Beschreibung der Leistung')),
                'quantity' => 1, 'unit' => 'Stk', 'unit_price' => '100', 'tax_rate' => $i <= $taxNotes ? 0 : 19,
            ] + ($i <= $taxNotes ? ['tax_note' => 'nicht steuerbar, Leistungsort Ausland'] : []);
        }
        $id = $this->as(Role::Buchhaltung)->postJson('/api/documents', array_merge([
            'type' => $type, 'customer_id' => $this->customer->id, 'service_date_from' => '2027-05-10', 'subject' => 'Busfahrt', 'lines' => $body,
        ], $over))->assertCreated()->json('id');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/$id/finalize")->assertOk();

        return Document::findOrFail($id);
    }

    /** Schlussrechnung mit Abzug einer Anzahlung. */
    private function finalInvoice(int $lines, int $descLines = 1): Document
    {
        $offer = $this->invoice($lines, $descLines, [], 0, 'angebot');
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$offer->id}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json('id');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/$order/finalize")->assertOk();
        $down = $this->as(Role::Buchhaltung)->postJson("/api/documents/$order/convert", ['type' => 'anzahlung'])->assertCreated()->json('id');
        $this->as(Role::Buchhaltung)->postJson("/api/documents/$down/finalize")->assertOk();
        $final = $this->invoiceDraftFrom($order)['id'];
        $this->as(Role::Buchhaltung)->postJson("/api/documents/$final/finalize")->assertOk();

        return Document::findOrFail($final);
    }

    /** Angebot mit der Hinfahrt und $sections Fahrten vor Ort ohne Tour (Fahrtbeschreibung). */
    private function quote(int $sections): Document
    {
        $disponent = User::factory()->create(['role' => Role::Disponent]);
        $q = $this->actingAs($disponent, 'sanctum')->postJson('/api/quotes/from-tour', [
            'loaded_km' => 400, 'empty_km' => 0, 'drive_s' => 18000, 'days' => 1, 'label' => 'München – Wien', 'vehicle_type' => 'doppeldecker', 'vehicle_count' => 1,
        ])->assertCreated()->json();
        $lines = $q['lines'];
        for ($i = 1; $i <= $sections; $i++) {
            $lines = $this->postJson("/api/documents/{$q['id']}/sections", [
                'kind' => 'vor_ort', 'own' => ['title' => "Ausflug $i", 'loaded_km' => 50, 'drive_minutes' => 120, 'days' => 1],
                'vehicles' => [['type' => 'doppeldecker', 'count' => 1]],
            ])->assertCreated()->json('lines');
        }
        $this->putJson("/api/documents/{$q['id']}", ['customer_id' => $this->customer->id, 'lines' => $lines])->assertOk();
        $this->as(Role::Buchhaltung)->postJson("/api/documents/{$q['id']}/finalize")->assertOk();

        return Document::findOrFail($q['id']);
    }

    /** Die Regeln für alle Seiten eines Belegs. @return list<string> gefundene Verstöße */
    private function violations(string $label, array $pages): array
    {
        $bad = [];
        $total = count($pages);
        foreach ($pages as $i => $text) {
            $n = $i + 1;
            $where = "$label, Seite $n von $total";
            if (! str_contains($text, 'Hafenstr. 5') || ! str_contains($text, 'IBAN')) {
                $bad[] = "$where: Fußzeile fehlt";
            }
            if (! preg_match('/Seite\s+'.$n.'\s+von\s+'.$total.'\b/', $text)) {
                $bad[] = "$where: „Seite $n von $total“ fehlt oder stimmt nicht";
            }
            $hasRows = (bool) preg_match('/Zeile \d\d/', $text);
            if ($hasRows && $n > 1 && ! str_contains($text, 'Bezeichnung')) {
                $bad[] = "$where: Tabellenüberschrift fehlt auf der Folgeseite";
            }
            if (str_contains($text, 'Nettobetrag') && ! $hasRows && ! preg_match('/(Hinfahrt|Fahrstrecke|Tagespauschale)/', $text)) {
                $bad[] = "$where: Summen allein auf der Seite";
            }
            $body = preg_split('/\R/', trim(preg_replace('/Busreisen Muster GmbH\s*Tel\..*$/s', '', $text)));
            $lastLine = trim((string) end($body));
            if ($lastLine === 'Fahrtbeschreibung') {
                $bad[] = "$where: Überschrift „Fahrtbeschreibung“ allein am Seitenende";
            }
            if ($n > 1 && ! $hasRows && ! str_contains($text, 'Nettobetrag') && ! str_contains($text, 'Fahrtbeschreibung')
                && ! preg_match('/(Hinfahrt|Fahrt vor Ort):/', $text) && str_contains($text, 'Danke für Ihr Vertrauen')) {
                $bad[] = "$where: Schlusstext allein auf der Seite";
            }
            // Ein Abschnitt der Fahrtbeschreibung hat Kopfzeile und Zahlenzeile: nie getrennt
            if (preg_match('/Fahrt vor Ort: Ausflug \d[^\n]*\s*$/', rtrim(preg_replace('/Busreisen Muster GmbH.*$/s', '', $text)))) {
                $bad[] = "$where: Fahrtbeschreibung-Abschnitt ohne Zahlenzeile am Seitenende";
            }
        }

        return $bad;
    }

    public function test_belege_mit_vielen_positionen_brechen_sauber_um(): void
    {
        $bad = [];
        foreach ([1, 2, 5, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 24, 28, 29, 30, 31, 32, 33, 34, 60, 90] as $lines) {
            $bad = array_merge($bad, $this->violations("$lines Positionen", $this->pages($this->invoice($lines, 2))));
        }
        $this->assertSame([], $bad, implode("\n", $bad));
    }

    public function test_belege_mit_kurzen_und_langen_positionen_brechen_sauber_um(): void
    {
        $bad = [];
        foreach ([[12, 0], [20, 0], [26, 0], [27, 0], [28, 0], [29, 0], [30, 0], [6, 5], [7, 5], [8, 5], [9, 5], [10, 5]] as [$lines, $desc]) {
            $bad = array_merge($bad, $this->violations("$lines Positionen à $desc Zeilen", $this->pages($this->invoice($lines, max(1, $desc)))));
        }
        $this->assertSame([], $bad, implode("\n", $bad));
    }

    public function test_schlussrechnung_mit_anzahlung_steuerhinweisen_und_langem_schlusstext(): void
    {
        $bad = [];
        $long = implode("\n", array_fill(0, 12, 'Zeile des Schlusstextes mit Hinweisen zu den Reisebedingungen.'));
        foreach ([3, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20] as $lines) {
            $bad = array_merge($bad, $this->violations("Schlussrechnung $lines Positionen", $this->pages($this->finalInvoice($lines, 2))));
            $bad = array_merge($bad, $this->violations("$lines Positionen mit 3 Steuerhinweisen", $this->pages($this->invoice($lines, 2, [], 3))));
            $bad = array_merge($bad, $this->violations("$lines Positionen mit langem Schlusstext", $this->pages($this->invoice($lines, 2, ['footer_text' => $long]))));
        }
        $this->assertSame([], $bad, implode("\n", $bad));
    }

    public function test_storno_bricht_sauber_um(): void
    {
        $bad = [];
        foreach ([9, 13, 16, 19] as $lines) {
            $invoice = $this->invoice($lines, 2);
            $storno = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$invoice->id}/cancel")->assertCreated()->json('id');
            $bad = array_merge($bad, $this->violations("Storno $lines Positionen", $this->pages(Document::findOrFail($storno))));
        }
        $this->assertSame([], $bad, implode("\n", $bad));
    }

    public function test_fahrtbeschreibung_mit_vielen_strecken_bricht_sauber_um(): void
    {
        $bad = [];
        foreach ([0, 1, 2, 3, 4, 5, 6, 7, 8, 12] as $sections) {
            $bad = array_merge($bad, $this->violations("$sections Strecken", $this->pages($this->quote($sections))));
        }
        $this->assertSame([], $bad, implode("\n", $bad));
    }
}
