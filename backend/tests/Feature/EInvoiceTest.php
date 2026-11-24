<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Database\Seeders\SettingsSeeder;
use horstoeko\zugferd\ZugferdDocumentReader;
use horstoeko\zugferd\ZugferdXsdValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInvoiceDrafts;
use Tests\TestCase;

/**
 * E-Rechnung (XRechnung 3.0, Syntax CII) für Kunden mit gesetztem Kennzeichen „E-Rechnung statt PDF“.
 * Feldzuordnung: docs/fachlich/erechnung-xrechnung.md.
 */
class EInvoiceTest extends TestCase
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
            'name' => 'Busreisen Muster GmbH', 'street' => 'Hafenstr. 5', 'postal_code' => '20457', 'city' => 'Hamburg', 'country' => 'DE',
            'phone' => '040 123456', 'email' => 'rechnung@muster-reisen.test', 'tax_number' => '12/345/67890', 'vat_id' => 'DE123456789',
            'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'managing_director' => 'Erika Muster', 'payment_term_days' => 14,
        ]);
        $this->customer = Customer::factory()->create([
            'company_name' => 'Reisebüro Sonnenschein', 'email' => 'einkauf@sonnenschein.test', 'street' => 'Hauptstr. 1',
            'postal_code' => '80331', 'city' => 'München', 'country' => 'DE', 'einvoice' => true, 'buyer_reference' => null,
        ]);
    }

    private function as(Role $role): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]), 'sanctum');
    }

    private function line(array $override = []): array
    {
        return array_merge(['title' => 'Busfahrt München – Salzburg', 'quantity' => '1', 'unit' => 'Fahrt', 'unit_price' => '1000.00', 'tax_rate' => '19'], $override);
    }

    /** Entwurf anlegen, optional Kunde und Positionen ändern. */
    private function draft(string $type = 'rechnung', ?array $lines = null, ?Customer $customer = null): string
    {
        return $this->as(Role::Buchhaltung)->postJson('/api/documents', [
            'type' => $type, 'customer_id' => ($customer ?? $this->customer)->id, 'service_date_from' => '2027-05-10', 'service_date_to' => '2027-05-12',
            'subject' => 'Busfahrt Mai', 'lines' => $lines ?? [$this->line()],
        ])->assertCreated()->json('id');
    }

    private function finalize(string $id)
    {
        return $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/finalize");
    }

    private function xp(string $xml): DOMXPath
    {
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'Die XML ist nicht wohlgeformt.');
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xp->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xp->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        return $xp;
    }

    private function value(DOMXPath $xp, string $path): ?string
    {
        $n = $xp->query($path);

        return $n->length ? trim($n->item(0)->textContent) : null;
    }

    private function xml(string $id): string
    {
        return $this->as(Role::Buchhaltung)->get("/api/documents/{$id}/einvoice")->assertOk()->getContent();
    }

    // ---------- Erzeugen beim Festschreiben ----------

    public function test_rechnung_erzeugt_beim_festschreiben_eine_xrechnung(): void
    {
        $id = $this->draft();
        $doc = $this->finalize($id)->assertOk()->assertJsonPath('einvoice_format', 'xrechnung')->json();

        $xml = $this->xml($id);
        $xp = $this->xp($xml);

        $this->assertSame($doc['number'], $this->value($xp, '//rsm:ExchangedDocument/ram:ID'));
        $this->assertSame('380', $this->value($xp, '//rsm:ExchangedDocument/ram:TypeCode'));
        $this->assertSame('urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0', $this->value($xp, '//ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'));
        $this->assertSame('1190.00', $this->value($xp, '//ram:GrandTotalAmount'));
        $this->assertSame('1190.00', $this->value($xp, '//ram:DuePayableAmount'));
        $this->assertSame('1000.00', $this->value($xp, '//ram:TaxBasisTotalAmount'));
        $this->assertSame('190.00', $this->value($xp, '//ram:TaxTotalAmount'));
        $this->assertSame('DE123456789', $this->value($xp, "//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID='VA']"));
        $this->assertSame('12/345/67890', $this->value($xp, "//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID='FC']"));
        $this->assertSame('DE02120300000000202051', $this->value($xp, '//ram:PayeePartyCreditorFinancialAccount/ram:IBANID'));
        $this->assertSame('rechnung@muster-reisen.test', $this->value($xp, '//ram:SellerTradeParty/ram:DefinedTradeContact/ram:EmailURIUniversalCommunication/ram:URIID'));
        $this->assertSame('Reisebüro Sonnenschein', $this->value($xp, '//ram:BuyerTradeParty/ram:Name'));
        // ohne Leitweg-ID: Käuferreferenz = Kundennummer, elektronische Adresse = E-Mail
        $this->assertSame($this->customer->customer_number, $this->value($xp, '//ram:ApplicableHeaderTradeAgreement/ram:BuyerReference'));
        $this->assertSame('einkauf@sonnenschein.test', $this->value($xp, '//ram:BuyerTradeParty/ram:URIUniversalCommunication/ram:URIID'));
        // Leistungszeitraum
        $this->assertSame('20270510', $this->value($xp, '//ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString'));
        $this->assertSame('20270512', $this->value($xp, '//ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString'));
        // Position
        $this->assertSame('1', $this->value($xp, '//ram:IncludedSupplyChainTradeLineItem/ram:AssociatedDocumentLineDocument/ram:LineID'));
        $this->assertSame('19.00', $this->value($xp, '//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent'));
    }

    public function test_registerangabe_und_geschaeftsfuehrung_stehen_als_rechtliche_information_in_der_xml(): void
    {
        CompanySetting::current()->update(['commercial_register' => 'Amtsgericht Hamburg, HRB 12345']);
        $id = $this->draft();
        $this->finalize($id)->assertOk();

        $xml = $this->xml($id);
        $this->assertSame(
            'Amtsgericht Hamburg, HRB 12345, Geschäftsführung: Erika Muster',
            $this->value($this->xp($xml), '//ram:SellerTradeParty/ram:Description'),
        );
    }

    public function test_die_erzeugte_xml_entspricht_dem_schema(): void
    {
        $id = $this->draft('rechnung', [$this->line(), $this->line(['title' => 'Maut', 'quantity' => '2.5', 'unit' => 'km', 'unit_price' => '1.8'])]);
        $this->finalize($id)->assertOk();

        $reader = ZugferdDocumentReader::readAndGuessFromContent($this->xml($id));
        $xsd = (new ZugferdXsdValidator($reader))->validate();

        $this->assertTrue($xsd->hasNoValidationErrors(), implode("\n", $xsd->validationErrors()));
    }

    public function test_kunde_ohne_kennzeichen_bekommt_beim_festschreiben_keine_xml(): void
    {
        $plain = Customer::factory()->create(['company_name' => 'Privat GmbH', 'street' => 'Weg 1', 'postal_code' => '10115', 'city' => 'Berlin', 'einvoice' => false]);
        $id = $this->draft('rechnung', null, $plain);

        $this->finalize($id)->assertOk()->assertJsonPath('einvoice_format', null);
        $this->assertNull(Document::find($id)->einvoice_path);
    }

    public function test_angebot_hat_keine_e_rechnung(): void
    {
        $id = $this->draft('angebot');
        $this->finalize($id)->assertOk()->assertJsonPath('einvoice_format', null);

        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$id}/einvoice")->assertUnprocessable();
    }

    // ---------- Pflichtangaben ----------

    public function test_fehlende_firmenangaben_verhindern_das_festschreiben_ohne_nummer_zu_verbrauchen(): void
    {
        CompanySetting::current()->update(['iban' => null, 'phone' => null]);
        $id = $this->draft();

        $response = $this->finalize($id)->assertUnprocessable();
        $messages = implode(' ', $response->json('errors.document'));
        $this->assertStringContainsString('IBAN', $messages);
        $this->assertStringContainsString('Telefonnummer', $messages);

        $doc = Document::find($id);
        $this->assertTrue($doc->isDraft());
        $this->assertNull($doc->number);
        $this->assertNull($doc->einvoice_path);
        Storage::disk('local')->assertMissing('archive/einvoices/'.$id.'.xml');

        // nachgetragen: jetzt geht es
        CompanySetting::current()->update(['iban' => 'DE02120300000000202051', 'phone' => '040 123456']);
        $this->finalize($id)->assertOk()->assertJsonPath('einvoice_format', 'xrechnung');
    }

    public function test_ohne_email_oder_leitweg_id_beim_kunden_wird_nicht_festgeschrieben(): void
    {
        $this->customer->update(['email' => null]);
        $id = $this->draft();

        $this->finalize($id)->assertUnprocessable();
        $this->assertStringContainsString('E-Mail-Adresse oder eine Leitweg-ID', implode(' ', $this->finalize($id)->json('errors.document')));
    }

    public function test_leitweg_id_ersetzt_die_email_und_wird_zur_kaeuferreferenz(): void
    {
        $this->customer->update(['email' => null, 'buyer_reference' => '04011000-1234512345-06']);
        $id = $this->draft();
        $this->finalize($id)->assertOk();

        $xp = $this->xp($this->xml($id));
        $this->assertSame('04011000-1234512345-06', $this->value($xp, '//ram:ApplicableHeaderTradeAgreement/ram:BuyerReference'));
        $this->assertSame('04011000-1234512345-06', $this->value($xp, '//ram:BuyerTradeParty/ram:URIUniversalCommunication/ram:URIID'));
        $this->assertSame('0204', $xp->query('//ram:BuyerTradeParty/ram:URIUniversalCommunication/ram:URIID/@schemeID')->item(0)->nodeValue);
    }

    // ---------- Belegarten ----------

    public function test_anzahlungsrechnung_hat_den_typ_386(): void
    {
        $id = $this->draft('anzahlung');
        $this->finalize($id)->assertOk();

        $this->assertSame('386', $this->value($this->xp($this->xml($id)), '//rsm:ExchangedDocument/ram:TypeCode'));
    }

    public function test_stornorechnung_geht_als_gutschriftsanzeige_mit_positiven_betraegen_und_verweis(): void
    {
        $id = $this->draft();
        $original = $this->finalize($id)->assertOk()->json();
        $storno = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$id}/cancel")->assertCreated()->assertJsonPath('einvoice_format', 'xrechnung')->json();

        $xp = $this->xp($this->xml($storno['id']));
        $this->assertSame('381', $this->value($xp, '//rsm:ExchangedDocument/ram:TypeCode'));
        $this->assertSame('1190.00', $this->value($xp, '//ram:GrandTotalAmount'));          // im Beleg −1190, in der XML positiv
        $this->assertSame('1.00', $this->value($xp, '//ram:SpecifiedLineTradeDelivery/ram:BilledQuantity'));
        $this->assertSame($original['number'], $this->value($xp, '//ram:InvoiceReferencedDocument/ram:IssuerAssignedID'));
        $this->assertNull($this->value($xp, '//ram:SpecifiedTradeSettlementPaymentMeans'));  // keine Zahlungsangaben bei der Stornorechnung

        $reader = ZugferdDocumentReader::readAndGuessFromContent($this->xml($storno['id']));
        $this->assertTrue((new ZugferdXsdValidator($reader))->validate()->hasNoValidationErrors());
    }

    public function test_schlussrechnung_nennt_vorausgezahlten_betrag_und_die_anzahlungen(): void
    {
        $offer = $this->draft('angebot');
        $this->finalize($offer)->assertOk();
        $order = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$offer}/convert", ['type' => 'auftragsbestaetigung'])->assertCreated()->json('id');
        $this->finalize($order)->assertOk();
        $advance = $this->as(Role::Buchhaltung)->postJson("/api/documents/{$order}/convert", ['type' => 'anzahlung'])->assertCreated()->json('id');
        $advanceNumber = $this->finalize($advance)->assertOk()->json('number');
        $final = $this->invoiceDraftFrom($order)['id'];
        $this->finalize($final)->assertOk();

        $xp = $this->xp($this->xml($final));
        $this->assertSame('1190.00', $this->value($xp, '//ram:GrandTotalAmount'));
        $this->assertSame('357.00', $this->value($xp, '//ram:TotalPrepaidAmount'));
        $this->assertSame('833.00', $this->value($xp, '//ram:DuePayableAmount'));
        $this->assertSame($advanceNumber, $this->value($xp, '//ram:InvoiceReferencedDocument/ram:IssuerAssignedID'));
    }

    // ---------- Steuerkategorien ----------

    public function test_steuerfreie_und_nicht_steuerbare_positionen_bekommen_kategorie_und_grund(): void
    {
        $id = $this->draft('rechnung', [
            $this->line(),
            $this->line(['title' => 'Fahrt in Österreich', 'unit_price' => '500.00', 'tax_rate' => '0', 'tax_note' => 'Ort der Leistung im Ausland, nicht steuerbar']),
            $this->line(['title' => 'Auslagen', 'unit_price' => '50.00', 'tax_rate' => '0', 'tax_note' => 'steuerfrei nach § 4 UStG']),
        ]);
        $this->finalize($id)->assertOk();
        $xml = $this->xml($id);
        $xp = $this->xp($xml);

        $this->assertSame('1550.00', $this->value($xp, '//ram:TaxBasisTotalAmount'));
        $categories = [];
        foreach ($xp->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax') as $tax) {
            $categories[$xp->evaluate('string(ram:CategoryCode)', $tax)] = $xp->evaluate('string(ram:ExemptionReason)', $tax);
        }
        $this->assertEqualsCanonicalizing(['S', 'E', 'O'], array_keys($categories));
        $this->assertSame('Ort der Leistung im Ausland, nicht steuerbar', $categories['O']);
        $this->assertSame('steuerfrei nach § 4 UStG', $categories['E']);

        $reader = ZugferdDocumentReader::readAndGuessFromContent($xml);
        $xsd = (new ZugferdXsdValidator($reader))->validate();
        $this->assertTrue($xsd->hasNoValidationErrors(), implode("\n", $xsd->validationErrors()));
    }

    public function test_einheiten_werden_nach_un_ece_abgebildet(): void
    {
        $id = $this->draft('rechnung', [
            $this->line(['unit' => 'km', 'quantity' => '100']), $this->line(['unit' => 'Tag']), $this->line(['unit' => 'Std']), $this->line(['unit' => 'pauschal']),
        ]);
        $this->finalize($id)->assertOk();

        $units = [];
        foreach ($this->xp($this->xml($id))->query('//ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode') as $a) {
            $units[] = $a->nodeValue;
        }
        $this->assertSame(['KMT', 'DAY', 'HUR', 'C62'], $units);
    }

    // ---------- Archiv, Download, Nacherzeugung ----------

    public function test_xml_wird_archiviert_nie_neu_erzeugt_und_ihre_pruefsumme_wird_geprueft(): void
    {
        $id = $this->draft();
        $this->finalize($id)->assertOk();
        $doc = Document::find($id);
        $first = $this->xml($id);

        // Stammdaten ändern sich: Die abgelegte E-Rechnung bleibt, wie sie war.
        $this->customer->update(['company_name' => 'Ganz Anders GmbH']);
        CompanySetting::current()->update(['iban' => 'DE89370400440532013000']);
        $this->assertSame($first, $this->xml($id));
        $this->assertSame(hash('sha256', $first), $doc->einvoice_sha256);

        // Beschädigte Datei: kein stilles Neuerzeugen
        Storage::disk('local')->put($doc->einvoice_path, $first.' ');
        $this->as(Role::Buchhaltung)->get("/api/documents/{$id}/einvoice")->assertStatus(500);
    }

    public function test_aeltere_rechnung_bekommt_die_xml_beim_ersten_abruf_einmal(): void
    {
        $plain = Customer::factory()->create([
            'company_name' => 'Spaeter GmbH', 'email' => 'x@spaeter.test', 'street' => 'Weg 1', 'postal_code' => '10115', 'city' => 'Berlin', 'einvoice' => false,
        ]);
        $id = $this->draft('rechnung', null, $plain);
        $this->finalize($id)->assertOk();
        $this->assertNull(Document::find($id)->einvoice_path);

        // Kunde wird später auf E-Rechnung umgestellt, Käuferreferenz nachgetragen
        $plain->update(['einvoice' => true, 'buyer_reference' => '04011000-1234512345-06']);

        $xml = $this->xml($id);
        $this->assertSame('04011000-1234512345-06', $this->value($this->xp($xml), '//ram:ApplicableHeaderTradeAgreement/ram:BuyerReference'));
        $this->assertNotNull(Document::find($id)->einvoice_path);
        $this->assertSame($xml, $this->xml($id));   // zweiter Abruf: dieselbe Datei
        $this->assertDatabaseHas('audit_logs', ['event' => 'einvoice_created']);
    }

    public function test_nacherzeugung_nennt_fehlende_angaben(): void
    {
        $plain = Customer::factory()->create(['company_name' => 'Ohne Mail GmbH', 'email' => null, 'street' => 'Weg 1', 'postal_code' => '10115', 'city' => 'Berlin', 'einvoice' => false]);
        $id = $this->draft('rechnung', null, $plain);
        $this->finalize($id)->assertOk();

        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$id}/einvoice")->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->assertNull(Document::find($id)->einvoice_path);
    }

    public function test_entwurf_und_rechte(): void
    {
        $draft = $this->draft();
        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$draft}/einvoice")->assertUnprocessable();

        $id = $this->draft();
        $this->finalize($id)->assertOk();
        $this->as(Role::Fahrer)->getJson("/api/documents/{$id}/einvoice")->assertForbidden();
        $this->as(Role::Disponent)->get("/api/documents/{$id}/einvoice")->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function test_zugferd_pdf_enthaelt_die_archivierte_xml(): void
    {
        $id = $this->draft();
        $this->finalize($id)->assertOk();
        $xml = $this->xml($id);

        $response = $this->as(Role::Buchhaltung)->get("/api/documents/{$id}/zugferd")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);

        // Die eingebettete XML lässt sich wieder auslesen und ist die archivierte (gleiche Belegnummer und Summe).
        $embedded = \horstoeko\zugferd\ZugferdDocumentPdfReader::getXmlFromContent($pdf);
        $this->assertSame(
            $this->value($this->xp($xml), '//rsm:ExchangedDocument/ram:ID'),
            $this->value($this->xp($embedded), '//rsm:ExchangedDocument/ram:ID'),
        );
        $this->assertSame(Document::find($id)->number, $this->value($this->xp($embedded), '//rsm:ExchangedDocument/ram:ID'));
        // Metadaten, die Prüfdienste für PDF/A-3 und ZUGFeRD erwarten
        $this->assertStringContainsString('pdfaid:part>3<', $pdf);
        $this->assertMatchesRegularExpression('/AFRelationship\s*\/(Data|Alternative|Source)/', $pdf);
        $this->assertStringContainsString('xrechnung.xml', $pdf);
        $this->assertDatabaseHas('audit_logs', ['event' => 'zugferd_downloaded']);
    }

    public function test_zugferd_nur_fuer_festgeschriebene_rechnungen_und_mit_rechten(): void
    {
        $draft = $this->draft();
        $this->as(Role::Buchhaltung)->getJson("/api/documents/{$draft}/zugferd")->assertUnprocessable();

        $id = $this->draft();
        $this->finalize($id)->assertOk();
        $this->as(Role::Fahrer)->getJson("/api/documents/{$id}/zugferd")->assertForbidden();
    }

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $this->getJson('/api/documents/01a203cb-c244-7000-8000-000000000000/einvoice')->assertUnauthorized();
    }

    public function test_eingefrorener_beleg_laesst_die_archivfelder_nicht_noch_einmal_aendern(): void
    {
        $id = $this->draft();
        $this->finalize($id)->assertOk();
        $doc = Document::find($id);

        $this->expectException(\LogicException::class);
        $doc->einvoice_sha256 = str_repeat('0', 64);
        $doc->save();
    }
}
