<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Document;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Der eine Weg, Belege anzulegen, festzuschreiben, zu stornieren und weiterzuführen.
 * Andere Module (Angebot ← Streckenplanung, Disposition …) rufen nur diesen Dienst auf und greifen
 * nie direkt auf die Tabellen zu.
 */
class DocumentService
{
    public function __construct(
        private readonly NumberRangeService $numbers,
        private readonly DocumentCalculator $calculator,
    ) {}

    /**
     * Neuer Entwurf. $lines: Liste mit title, description, quantity, unit, unit_price, tax_rate, tax_note.
     */
    public function createDraft(DocumentType $type, array $data, array $lines = []): Document
    {
        return DB::transaction(function () use ($type, $data, $lines) {
            $document = new Document($data);
            $document->forceFill(['type' => $type, 'status' => DocumentStatus::Entwurf])->save();

            $this->replaceLines($document, $lines);

            return $document->fresh(['lines', 'customer']);
        });
    }

    /** Entwurf ändern (Kopfdaten und alle Positionen). Festgeschriebene Belege sind gesperrt. */
    public function updateDraft(Document $document, array $data, array $lines): Document
    {
        return DB::transaction(function () use ($document, $data, $lines) {
            $document = $this->lockDraft($document);
            $document->fill($data)->save();
            $this->replaceLines($document, $lines);

            return $document->fresh(['lines', 'customer']);
        });
    }

    /** Entwurf löschen. Erlaubt, weil noch keine Nummer vergeben ist (keine Lücke im Nummernkreis). */
    public function deleteDraft(Document $document): void
    {
        DB::transaction(function () use ($document) {
            // Das Protokoll schreibt das Modell selbst (Auditable).
            $this->lockDraft($document)->delete();
        });
    }

    /**
     * Festschreiben: Pflichtangaben prüfen, Absender und Empfänger kopieren, Nummer vergeben, sperren.
     * Nummer und Sperre passieren in EINER Transaktion: Schlägt etwas fehl, bleibt der Entwurf und
     * keine Nummer wird verbraucht.
     */
    public function finalize(Document $document): Document
    {
        return DB::transaction(function () use ($document) {
            $document = $this->lockDraft($document);
            $document->load(['lines', 'customer']);

            $company = CompanySetting::current();
            $customer = $document->customer;

            $errors = $this->validateForFinalize($document, $company, $customer);
            if ($errors !== []) {
                throw ValidationException::withMessages(['document' => $errors]);
            }

            $date = $document->document_date ?? Carbon::today();
            $terms = $document->payment_term_days ?? $customer->payment_term_days ?? $company->payment_term_days;

            $document->forceFill([
                'seller' => $this->sellerSnapshot($company),
                'recipient' => $this->recipientSnapshot($customer),
                'document_date' => $date,
                'payment_term_days' => $document->type->isInvoice() ? $terms : $document->payment_term_days,
                'due_date' => $document->type->isInvoice() ? Carbon::parse($date)->addDays((int) $terms) : null,
                'currency' => 'EUR',
            ]);

            return $this->issue($document);
        });
    }

    /**
     * Rechnung stornieren: erzeugt einen eigenen, sofort festgeschriebenen Storno-Beleg (Gutschrift) mit
     * negativen Mengen. Der Original-Beleg bleibt unverändert, nur der Verweis auf den Storno wird gesetzt.
     */
    public function cancel(Document $document): Document
    {
        return DB::transaction(function () use ($document) {
            $original = Document::lockForUpdate()->findOrFail($document->id);
            $original->load('lines');

            if (! $original->type->isInvoice() || $original->type === DocumentType::Gutschrift) {
                $this->fail('Nur Rechnungen und Anzahlungsrechnungen können storniert werden.');
            }
            if ($original->isDraft()) {
                $this->fail('Ein Entwurf wird nicht storniert, sondern gelöscht.');
            }
            if ($original->cancelled_by_id !== null) {
                $this->fail('Dieser Beleg ist bereits storniert.');
            }

            $storno = new Document;
            $storno->forceFill([
                'type' => DocumentType::Gutschrift,
                'status' => DocumentStatus::Entwurf,
                'customer_id' => $original->customer_id,
                'source_id' => $original->id,
                'cancels_id' => $original->id,
                // Dieselben Angaben wie im Original: Absender und Empfänger werden NICHT neu aus den Stammdaten geholt.
                'seller' => $original->seller,
                'recipient' => $original->recipient,
                'subject' => 'Storno zu '.$original->number,
                'service_date_from' => $original->service_date_from,
                'service_date_to' => $original->service_date_to,
                'document_date' => Carbon::today(),
                'currency' => $original->currency,
            ])->save();

            $lines = $original->lines->map(fn ($line) => [
                'title' => $line->title,
                'description' => $line->description,
                'quantity' => $this->negate((string) $line->quantity),
                'unit' => $line->unit,
                'unit_price' => (string) $line->unit_price,
                'tax_rate' => (string) $line->tax_rate,
                'tax_note' => $line->tax_note,
            ])->all();
            $this->replaceLines($storno, $lines);

            $storno = $this->issue($storno);

            $original->forceFill(['cancelled_by_id' => $storno->id])->save();
            AuditLog::record('cancelled', $original, null, ['cancelled_by_id' => $storno->id, 'number' => $storno->number]);

            return $storno->fresh(['lines', 'customer']);
        });
    }

    /**
     * Folgebeleg als neuen Entwurf aus einem festgeschriebenen Beleg (Angebot → Auftragsbestätigung → Rechnung).
     * Kunde, Texte und Positionen werden kopiert, der Verweis auf den Vorgänger bleibt (source_id).
     */
    public function convert(Document $source, DocumentType $target): Document
    {
        return DB::transaction(function () use ($source, $target) {
            $source->load('lines');

            if ($source->isDraft()) {
                $this->fail('Bitte den Beleg zuerst festschreiben.');
            }
            if (! in_array($target, $source->type->convertibleTo(), true)) {
                $this->fail("Aus {$source->type->label()} kann kein(e) {$target->label()} erstellt werden.");
            }

            $document = new Document([
                'customer_id' => $source->customer_id,
                'subject' => $source->subject,
                'intro_text' => $source->intro_text,
                'footer_text' => $source->footer_text,
                'service_date_from' => $source->service_date_from,
                'service_date_to' => $source->service_date_to,
            ]);
            $document->forceFill(['type' => $target, 'status' => DocumentStatus::Entwurf, 'source_id' => $source->id])->save();

            $this->replaceLines($document, $source->lines->map(fn ($l) => [
                'title' => $l->title, 'description' => $l->description, 'quantity' => (string) $l->quantity,
                'unit' => $l->unit, 'unit_price' => (string) $l->unit_price,
                'tax_rate' => (string) $l->tax_rate, 'tax_note' => $l->tax_note,
            ])->all());

            return $document->fresh(['lines', 'customer']);
        });
    }

    // ---------- intern ----------

    /** Nummer vergeben und sperren. Nur innerhalb einer Transaktion aufrufen. */
    private function issue(Document $document): Document
    {
        $number = $this->numbers->next($document->type->numberRange());

        $document->forceFill([
            'number' => $number,
            'status' => DocumentStatus::Festgeschrieben,
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
        ])->save();

        AuditLog::record('finalized', $document, null, ['number' => $number, 'type' => $document->type->value]);

        return $document;
    }

    /** Beleg mit Zeilensperre laden und sicherstellen, dass er noch ein Entwurf ist. */
    private function lockDraft(Document $document): Document
    {
        $locked = Document::lockForUpdate()->findOrFail($document->id);
        if (! $locked->isDraft()) {
            $this->fail('Dieser Beleg ist bereits festgeschrieben und kann nicht mehr geändert werden.');
        }

        return $locked;
    }

    private function replaceLines(Document $document, array $lines): void
    {
        $document->lines()->delete();
        foreach (array_values($lines) as $i => $line) {
            $row = $document->lines()->make(array_intersect_key($line, array_flip([
                'title', 'description', 'quantity', 'unit', 'unit_price', 'tax_rate', 'tax_note',
            ])));
            $row->unit = $row->unit ?: 'Stk';
            $row->position = $i + 1;
            $row->save();
        }
        $this->calculator->apply($document);
    }

    /** Pflichtangaben nach § 14 Abs. 4 UStG (Rechnungen) bzw. Mindestangaben (Angebot, Auftragsbestätigung). */
    private function validateForFinalize(Document $document, CompanySetting $company, ?Customer $customer): array
    {
        $errors = [];
        $isInvoice = $document->type->isInvoice();

        if ($customer === null) {
            $errors[] = 'Bitte einen Kunden auswählen.';
        } else {
            if (blank($customer->display_name)) {
                $errors[] = 'Beim Kunden fehlt der Name.';
            }
            if ($isInvoice && (blank($customer->street) || blank($customer->postal_code) || blank($customer->city))) {
                $errors[] = 'Beim Kunden fehlt die Anschrift (Straße, PLZ, Ort).';
            }
        }

        if ($document->lines->isEmpty()) {
            $errors[] = 'Der Beleg hat keine Positionen.';
        }
        foreach ($document->lines as $line) {
            if (blank($line->title)) {
                $errors[] = "Position {$line->position}: Bezeichnung fehlt.";
            }
            if ((float) $line->quantity == 0.0) {
                $errors[] = "Position {$line->position}: Menge darf nicht 0 sein.";
            }
            if ($isInvoice && (float) $line->tax_rate == 0.0 && blank($line->tax_note)) {
                $errors[] = "Position {$line->position}: Bei 0 % Steuer bitte den Grund angeben (z. B. steuerfrei, Ausland).";
            }
        }

        if ($isInvoice) {
            if (blank($company->name) || blank($company->street) || blank($company->postal_code) || blank($company->city)) {
                $errors[] = 'In den Firmendaten fehlen Name oder Anschrift.';
            }
            if (blank($company->tax_number) && blank($company->vat_id)) {
                $errors[] = 'In den Firmendaten fehlt die Steuernummer oder USt-IdNr.';
            }
            if ($document->type !== DocumentType::Anzahlung && $document->type !== DocumentType::Gutschrift
                && $document->service_date_from === null) {
                $errors[] = 'Bitte den Leistungszeitpunkt (Fahrtdatum) angeben.';
            }
            if ($document->service_date_to !== null && $document->service_date_from !== null
                && $document->service_date_to->lt($document->service_date_from)) {
                $errors[] = 'Das Ende der Leistung liegt vor dem Beginn.';
            }
        }

        return $errors;
    }

    private function sellerSnapshot(CompanySetting $company): array
    {
        return $company->only([
            'name', 'legal_form', 'street', 'postal_code', 'city', 'country', 'phone', 'email', 'website',
            'tax_number', 'vat_id', 'commercial_register', 'managing_director', 'bank_name', 'iban', 'bic',
        ]);
    }

    private function recipientSnapshot(Customer $customer): array
    {
        return array_merge(
            $customer->only([
                'customer_number', 'debitor_number', 'type', 'company_name', 'salutation', 'first_name', 'last_name',
                'street', 'postal_code', 'city', 'country', 'vat_id', 'email',
            ]),
            ['name' => $customer->display_name, 'type' => $customer->type->value],
        );
    }

    private function negate(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : '-'.$value;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document' => [$message]]);
    }
}
