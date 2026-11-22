<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\AuditLog;
use App\Models\Document;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\ZugferdXsdValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * E-Rechnung im Format XRechnung 3.0 (Syntax UN/CEFACT CII, Norm EN 16931) für ausgehende Rechnungen.
 *
 * Ablauf (siehe docs/fachlich/erechnung-xrechnung.md): Ist am Kunden „E-Rechnung statt PDF“ gesetzt, prüft `problems()` schon
 * vor dem Festschreiben, ob alle Pflichtangaben da sind. Beim Festschreiben entsteht die XML einmal, wird im privaten Speicher
 * abgelegt (Pfad und SHA-256 am Beleg) und danach unverändert ausgeliefert, nie neu erzeugt (GoBD, § 14b UStG).
 *
 * Die XML entsteht aus den **Schnappschüssen** des Belegs (`seller`, `recipient`), nicht aus den heutigen Stammdaten:
 * Ein später geänderter Kunde ändert die E-Rechnung nicht.
 *
 * Eine Stornorechnung geht als Gutschriftsanzeige (Typ 381) mit POSITIVEN Beträgen und Verweis auf die stornierte Rechnung hinaus,
 * so erwarten es Empfängersysteme; im Beleg selbst stehen die Beträge negativ.
 */
class EInvoiceService
{
    public const FORMAT = 'xrechnung';

    /** Leitweg-ID: Grobadresse, optional Feinadresse, zweistellige Prüfziffer (z. B. 04011000-1234512345-06). */
    private const LEITWEG = '/^\d{2,12}(-[A-Za-z0-9]{1,30})?-\d{2}$/';

    public static function pathFor(Document $document): string
    {
        return 'archive/einvoices/'.$document->id.'.xml';
    }

    /** Soll für diesen Beleg eine E-Rechnung entstehen: eine Rechnungsart und ein Empfänger, der sie wünscht. */
    public function wanted(DocumentType $type, array $recipient): bool
    {
        return $type->isInvoice() && ! empty($recipient['einvoice']);
    }

    /**
     * Fehlende oder unbrauchbare Angaben, in Klartext mit Hinweis, wo sie zu ergänzen sind. Leer = die XML lässt sich erzeugen.
     *
     * @param  array<string, mixed>  $seller     Absender (Firmendaten oder Schnappschuss)
     * @param  array<string, mixed>  $recipient  Empfänger (Kunde oder Schnappschuss)
     * @return list<string>
     */
    public function problems(Document $document, array $seller, array $recipient): array
    {
        $errors = [];

        foreach (['name' => 'der Firmenname', 'street' => 'die Straße', 'postal_code' => 'die PLZ', 'city' => 'der Ort'] as $key => $what) {
            if (blank($seller[$key] ?? null)) {
                $errors[] = "E-Rechnung: In den Firmendaten fehlt $what.";
            }
        }
        if (blank($seller['vat_id'] ?? null) && blank($seller['tax_number'] ?? null)) {
            $errors[] = 'E-Rechnung: In den Firmendaten fehlt die USt-IdNr. oder Steuernummer.';
        }
        if (blank($seller['phone'] ?? null)) {
            $errors[] = 'E-Rechnung: In den Firmendaten fehlt die Telefonnummer (Pflicht in der XRechnung).';
        }
        if (blank($seller['email'] ?? null)) {
            $errors[] = 'E-Rechnung: In den Firmendaten fehlt die E-Mail-Adresse (Pflicht in der XRechnung).';
        }
        if ($document->type !== DocumentType::Gutschrift && blank($seller['iban'] ?? null)) {
            $errors[] = 'E-Rechnung: In den Firmendaten fehlt die IBAN (Zahlungsangaben der Rechnung).';
        }

        $name = $recipient['name'] ?? $recipient['company_name'] ?? null;
        if (blank($name)) {
            $errors[] = 'E-Rechnung: Beim Kunden fehlt der Name.';
        }
        foreach (['street' => 'die Straße', 'postal_code' => 'die PLZ', 'city' => 'der Ort'] as $key => $what) {
            if (blank($recipient[$key] ?? null)) {
                $errors[] = "E-Rechnung: Beim Kunden fehlt $what.";
            }
        }
        if (blank($recipient['country'] ?? null)) {
            $errors[] = 'E-Rechnung: Beim Kunden fehlt das Land.';
        }
        // Elektronische Adresse des Käufers (BT-49): Bei einer Leitweg-ID gilt sie, sonst die E-Mail-Adresse.
        if (! $this->isLeitweg($recipient['buyer_reference'] ?? null) && blank($recipient['email'] ?? null)) {
            $errors[] = 'E-Rechnung: Beim Kunden fehlt die E-Mail-Adresse oder eine Leitweg-ID (Pflicht in der XRechnung).';
        }
        if (blank($this->buyerReference($recipient))) {
            $errors[] = 'E-Rechnung: Beim Kunden fehlt die Käuferreferenz (Leitweg-ID).';
        }

        return $errors;
    }

    /**
     * Die XRechnung-XML eines Belegs (Absender und Empfänger aus den Schnappschüssen, Nummer und Datum müssen gesetzt sein).
     *
     * @throws RuntimeException wenn die XML nicht dem Schema entspricht (Fehler im Programm, nicht in den Eingaben)
     */
    public function xml(Document $document, ?array $recipientOverride = null): string
    {
        $document->loadMissing(['lines', 'source']);
        $seller = (array) $document->seller;
        $recipient = $recipientOverride ?? (array) $document->recipient;
        $credit = $document->type === DocumentType::Gutschrift;

        $b = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3);
        $b->setDocumentInformation(
            (string) $document->number,
            match ($document->type) {
                DocumentType::Anzahlung => '386',   // Anzahlungsrechnung
                DocumentType::Gutschrift => '381',  // Gutschriftsanzeige (Stornorechnung)
                default => '380',                   // Rechnung
            },
            $document->document_date ?? Carbon::today(),
            $document->currency ?: 'EUR',
        );
        $b->setDocumentBuyerReference($this->buyerReference($recipient));
        if (filled($document->subject)) {
            $b->addDocumentNote((string) $document->subject);
        }

        // ---- Verkäufer (BG-4) ----
        $contact = $seller['managing_director'] ?? null ?: $seller['name'];
        // BT-33 „weitere rechtliche Informationen“: Registerangabe und Geschäftsführung, wie in der Fußzeile des PDFs
        // (Pflicht auf Geschäftsbriefen der GmbH, § 35a GmbHG).
        $legal = array_filter([
            filled($seller['commercial_register'] ?? null) ? trim((string) $seller['commercial_register']) : null,
            filled($seller['managing_director'] ?? null) ? 'Geschäftsführung: '.trim((string) $seller['managing_director']) : null,
        ]);
        $b->setDocumentSeller((string) $seller['name'], null, $legal === [] ? null : implode(', ', $legal));
        $b->setDocumentSellerAddress($seller['street'] ?? null, null, null, $seller['postal_code'] ?? null, $seller['city'] ?? null, $seller['country'] ?? 'DE');
        $b->setDocumentSellerContact((string) $contact, null, $seller['phone'] ?? null, null, $seller['email'] ?? null);
        $b->setDocumentSellerCommunication('EM', (string) ($seller['email'] ?? ''));
        if (filled($seller['vat_id'] ?? null)) {
            $b->addDocumentSellerTaxRegistration('VA', (string) $seller['vat_id']);
        }
        if (filled($seller['tax_number'] ?? null)) {
            $b->addDocumentSellerTaxRegistration('FC', (string) $seller['tax_number']);
        }

        // ---- Käufer (BG-7) ----
        $b->setDocumentBuyer((string) ($recipient['name'] ?? $recipient['company_name']), $recipient['customer_number'] ?? null);
        $b->setDocumentBuyerAddress($recipient['street'] ?? null, null, null, $recipient['postal_code'] ?? null, $recipient['city'] ?? null, $recipient['country'] ?? 'DE');
        if ($this->isLeitweg($recipient['buyer_reference'] ?? null)) {
            $b->setDocumentBuyerCommunication('0204', (string) $recipient['buyer_reference']);
        } else {
            $b->setDocumentBuyerCommunication('EM', (string) ($recipient['email'] ?? ''));
        }
        if (filled($recipient['vat_id'] ?? null)) {
            $b->addDocumentBuyerTaxRegistration('VA', (string) $recipient['vat_id']);
        }

        // ---- Leistungszeitraum (BG-14) ----
        if ($document->service_date_from !== null) {
            $b->setDocumentBillingPeriod($document->service_date_from, $document->service_date_to ?? $document->service_date_from, null);
        }

        // ---- Vorherige Rechnungen (BG-3): bei der Stornorechnung die stornierte, bei der Schlussrechnung die Anzahlungen ----
        if ($credit && $document->source !== null && filled($document->source->number)) {
            $b->addDocumentInvoiceReferencedDocument((string) $document->source->number, null, $document->source->document_date);
        }
        foreach ((array) $document->deductions as $d) {
            $b->addDocumentInvoiceReferencedDocument((string) $d['number'], null, filled($d['document_date'] ?? null) ? Carbon::parse($d['document_date']) : null);
        }

        // ---- Zahlung (BG-16): nicht bei der Stornorechnung ----
        if (! $credit) {
            $b->addDocumentPaymentMeanToCreditTransfer(
                str_replace(' ', '', (string) $seller['iban']), (string) $seller['name'], null,
                filled($seller['bic'] ?? null) ? (string) $seller['bic'] : null,
            );
            if ($document->due_date !== null) {
                $b->addDocumentPaymentTerm(
                    sprintf('Zahlbar innerhalb von %d Tagen bis zum %s ohne Abzug.', (int) $document->payment_term_days, $document->due_date->format('d.m.Y')),
                    $document->due_date,
                );
            }
        }

        // ---- Positionen (BG-25) ----
        $zeroGroups = [];   // Steuerfreie und nicht steuerbare Positionen: Nettosumme je Kategorie und Grund
        foreach ($document->lines as $line) {
            $rate = round((float) $line->tax_rate, 2);
            $qty = (float) $line->quantity;
            $price = (float) $line->unit_price;
            $net = (float) $line->net_amount;
            if ($credit) {
                [$qty, $price, $net] = [abs($qty), abs($price), abs($net)];
            } elseif ($price < 0) {
                // Ein Einzelpreis darf in der Norm nicht negativ sein: Das Vorzeichen wandert in die Menge.
                [$qty, $price] = [-$qty, -$price];
            }
            $category = $rate > 0 ? 'S' : $this->exemptCategory($line->tax_note);

            $b->addNewPosition((string) $line->position);
            $b->setDocumentPositionProductDetails((string) $line->title, filled($line->description) ? (string) $line->description : null);
            $b->setDocumentPositionNetPrice(round($price, 2));
            $b->setDocumentPositionQuantity($qty, $this->unitCode((string) $line->unit));
            // Kategorie O (nicht steuerbar) kennt keinen Satz, Kategorie E den Satz 0
            $b->addDocumentPositionTax($category, 'VAT', $category === 'O' ? null : $rate);
            $b->setDocumentPositionLineSummation(round($net, 2));

            if ($rate <= 0) {
                $reason = trim((string) $line->tax_note) !== '' ? trim((string) $line->tax_note) : ($category === 'O' ? 'Nicht steuerbar' : 'Steuerfrei');
                $key = $category.'|'.$reason;
                $zeroGroups[$key] = ['category' => $category, 'reason' => $reason, 'basis' => ($zeroGroups[$key]['basis'] ?? 0.0) + $net];
            }
        }

        // ---- Steuer je Satz (BG-23): Sätze über 0 aus der Beleg-Summe, steuerfreie Anteile je Kategorie und Grund ----
        foreach ((array) $document->tax_summary as $row) {
            if ((float) $row['rate'] > 0) {
                $b->addDocumentTax('S', 'VAT', round(abs((float) $row['net']), 2), round(abs((float) $row['tax']), 2), round((float) $row['rate'], 2));
            }
        }
        foreach ($zeroGroups as $g) {
            $b->addDocumentTax($g['category'], 'VAT', round(abs($g['basis']), 2), 0.0, $g['category'] === 'O' ? null : 0.0, $g['reason']);
        }

        // ---- Summen (BG-22) ----
        $abs = fn ($v) => round(abs((float) $v), 2);
        $prepaid = $abs($document->deduction_gross);
        $b->setDocumentSummation(
            $abs($document->gross_total), $abs($document->payable_total), $abs($document->net_total), null, null,
            $abs($document->net_total), $abs($document->tax_total), null, $prepaid > 0 ? $prepaid : null,
        );

        $xml = $b->getContent();

        // Die erzeugte Datei muss dem Schema des Formats entsprechen. Ein Fehler hier ist ein Fehler im Programm: lieber nicht
        // festschreiben als eine ungültige E-Rechnung ablegen.
        $xsd = (new ZugferdXsdValidator($b))->validate();
        if ($xsd->hasValidationErrors()) {
            report(new RuntimeException('XRechnung entspricht nicht dem Schema: '.implode(' | ', array_slice($xsd->validationErrors(), 0, 5))));
            throw new RuntimeException('Die E-Rechnung konnte nicht korrekt erzeugt werden.');
        }

        return $xml;
    }

    /**
     * Erzeugt die XML eines gerade festgeschriebenen Belegs, legt sie ab und setzt die Archivfelder am Modell (der Aufrufer
     * speichert). Wirft bei jedem Fehler, damit das Festschreiben zurückgerollt wird.
     */
    public function archive(Document $document, ?array $recipientOverride = null): void
    {
        $xml = $this->xml($document, $recipientOverride);
        $path = self::pathFor($document);
        if (! Storage::disk('local')->put($path, $xml)) {
            throw new RuntimeException('Die E-Rechnung konnte nicht abgelegt werden.');
        }

        $document->forceFill([
            'einvoice_path' => $path,
            'einvoice_sha256' => hash('sha256', $xml),
            'einvoice_format' => self::FORMAT,
            'einvoice_at' => now(),
        ]);
    }

    /** Räumt die Datei auf, wenn das Festschreiben nach dem Ablegen noch scheitert. */
    public function discard(Document $document): void
    {
        Storage::disk('local')->delete(self::pathFor($document));
    }

    /**
     * Die abgelegte XML nach Prüfung der Prüfsumme. Hat der Beleg noch keine (festgeschriebene Rechnung vor der E-Rechnung oder ein
     * Kunde, der erst später auf E-Rechnung umgestellt wurde), wird sie beim ersten Abruf einmal erzeugt und danach genauso behandelt.
     *
     * @throws \Illuminate\Validation\ValidationException wenn dabei Pflichtangaben fehlen
     * @throws RuntimeException wenn die Datei fehlt oder nicht zur Prüfsumme passt
     */
    public function read(Document $document): string
    {
        if ($document->einvoice_path === null) {
            $this->backfill($document);
        }

        $disk = Storage::disk('local');
        $xml = $disk->exists($document->einvoice_path) ? $disk->get($document->einvoice_path) : null;

        if ($xml === null || ! hash_equals((string) $document->einvoice_sha256, hash('sha256', $xml))) {
            AuditLog::record('einvoice_check_failed', $document, null, ['number' => $document->number, 'missing' => $xml === null]);
            throw new RuntimeException($xml === null
                ? 'Die E-Rechnung dieses Belegs fehlt im Speicher.'
                : 'Die E-Rechnung dieses Belegs stimmt nicht mehr mit ihrer Prüfsumme überein.');
        }

        return $xml;
    }

    /**
     * ZUGFeRD-Fassung (hybride Rechnung): das archivierte Beleg-PDF mit der archivierten XRechnung-XML als Anhang. Das Profil
     * XRechnung ist auch in ZUGFeRD zulässig, es braucht also keine zweite XML. Beide Quellen kommen nach Prüfsumme aus dem Archiv;
     * die Datei selbst wird nicht abgelegt, sondern bei jedem Abruf aus ihnen zusammengesetzt.
     *
     * @throws RuntimeException wenn eine der Quellen fehlt oder nicht zur Prüfsumme passt
     */
    public function hybridPdf(Document $document, DocumentArchive $archive): string
    {
        $xml = $this->read($document);
        $pdf = $archive->read($document);

        $merger = new ZugferdDocumentPdfMerger($xml, $pdf);
        $merger->generateDocument();

        return $merger->downloadString();
    }

    private function backfill(Document $document): void
    {
        $document->loadMissing('lines');
        $recipient = (array) $document->recipient;
        // Die Käuferreferenz kann seit dem Festschreiben am Kunden nachgetragen worden sein: Sie kommt dann von dort.
        // Nur für die XML: Der festgeschriebene Beleg (und sein Empfänger-Schnappschuss) bleibt unverändert.
        if (blank($recipient['buyer_reference'] ?? null) && $document->customer_id !== null) {
            $recipient['buyer_reference'] = \App\Models\Customer::withTrashed()->find($document->customer_id)?->buyer_reference;
        }

        $problems = $this->problems($document, (array) $document->seller, $recipient);
        if ($problems !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['document' => $problems]);
        }

        $this->archive($document, $recipient);
        $document->save();
        AuditLog::record('einvoice_created', $document, null, ['number' => $document->number]);
    }

    // ---------- intern ----------

    private function isLeitweg(?string $value): bool
    {
        return $value !== null && preg_match(self::LEITWEG, trim($value)) === 1;
    }

    /** Käuferreferenz (BT-10): die Leitweg-ID, sonst die Kundennummer. Eine Angabe verlangt die XRechnung immer. */
    private function buyerReference(array $recipient): ?string
    {
        $ref = trim((string) ($recipient['buyer_reference'] ?? ''));

        return $ref !== '' ? $ref : (filled($recipient['customer_number'] ?? null) ? (string) $recipient['customer_number'] : null);
    }

    /** E = steuerbefreit, O = nicht steuerbar (Ort der Leistung im Ausland u. Ä.). Der Text des Steuerhinweises entscheidet. */
    private function exemptCategory(?string $note): string
    {
        return preg_match('/nicht\s+steuerbar|außerhalb|ausland|nicht\s+im\s+inland/iu', (string) $note) === 1 ? 'O' : 'E';
    }

    /** Einheit der Position nach UN/ECE Recommendation 20. Unbekanntes ist ein Stück (C62). */
    private function unitCode(string $unit): string
    {
        return match (mb_strtolower(trim($unit))) {
            'km', 'kilometer' => 'KMT',
            'tag', 'tage', 'd' => 'DAY',
            'nacht', 'nächte', 'übernachtung' => 'DAY',
            'std', 'std.', 'stunde', 'stunden', 'h' => 'HUR',
            default => 'C62',
        };
    }
}
