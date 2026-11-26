<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Legt Demo-Belege an: Belegketten (Angebot → Auftragsbestätigung → Anzahlung/Rechnung → Storno) in allen Zuständen,
 * damit die Belegliste mit ihren Ketten, Zahlständen und Fälligkeiten Testdaten hat.
 *
 * Alles läuft über dieselben Wege wie in der Anwendung (DocumentService, PaymentService): echte Nummern aus den
 * Nummernkreisen, festgeschriebene Belege mit Archiv-PDF, Zahlungen mit Prüfung. Nur das Belegdatum wird in die Vergangenheit
 * gelegt. Ein zweiter Lauf legt nichts an, solange es Belege von Demo-Kunden gibt (Belege lassen sich nach dem Festschreiben
 * nicht mehr löschen, das ist Absicht; zum Neubeginn die Firmen-Datenbank neu aufbauen).
 */
class DemoDocumentService
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly PaymentService $payments,
    ) {}

    public function exists(): bool
    {
        return Document::whereHas('customer', fn ($q) => $q->where('is_demo', true))->exists();
    }

    /**
     * @return array{chains: int, documents: int, skipped: int, errors?: list<string>}
     */
    public function generate(): array
    {
        $stats = ['chains' => 0, 'documents' => 0, 'skipped' => 0];
        if ($this->exists()) {
            return $stats + ['skipped' => -1];
        }

        $customers = Customer::where('is_demo', true)
            ->whereNull('deleted_at')
            ->whereNotNull('street')->whereNotNull('postal_code')->whereNotNull('city')
            ->orderBy('customer_number')
            ->limit(5)
            ->get();
        if ($customers->count() < 1) {
            return $stats;
        }

        // Festschreiben und Zahlungen protokollieren, wer sie ausgelöst hat.
        if ($user = User::where('role', 'admin')->first() ?? User::first()) {
            Auth::setUser($user);
        }

        // [Kette, Tage seit dem Angebot]: jede Kette zeigt einen anderen Stand.
        $scenarios = [
            ['angebot_entwurf', 2], ['angebot', 6], ['bestaetigt', 12], ['rechnung_offen', 8], ['rechnung_ueberfaellig', 50],
            ['rechnung_bezahlt', 35], ['rechnung_teilweise', 45], ['anzahlung_rechnung', 40], ['storniert', 30], ['rechnung_offen', 20],
        ];

        foreach ($scenarios as $n => [$kind, $days]) {
            $customer = $customers[$n % $customers->count()];
            $start = Carbon::today()->subDays($days);
            $price = [850, 1240, 2380, 460, 3150, 1780, 990, 2640, 1325, 510][$n];

            try {
                $count = $this->chain($kind, $customer, $start, (string) $price, $n);
            } catch (ValidationException $e) {
                $stats['errors'][] = $kind.': '.collect($e->errors())->flatten()->implode(' ');
                $stats['skipped']++;

                continue;
            }
            $stats['chains']++;
            $stats['documents'] += $count;
        }

        AuditLog::record('demo_documents_created', null, null, $stats);

        return $stats;
    }

    /** Eine Kette anlegen; liefert die Zahl der Belege darin. */
    private function chain(string $kind, Customer $customer, Carbon $start, string $price, int $n): int
    {
        $count = 1;
        $offer = $this->documents->createDraft(DocumentType::Angebot, [
            'customer_id' => $customer->id,
            'subject' => ['Busfahrt Ulm – Salzburg', 'Klassenfahrt Berlin', 'Vereinsausflug Bodensee', 'Tagesfahrt Europa-Park', 'Busfahrt Hamburg – Sylt'][$n % 5],
            'service_date_from' => $start->copy()->addDays(40)->toDateString(),
            'document_date' => $start->toDateString(),
        ], [['title' => 'Busfahrt, 1 Reisebus', 'quantity' => 1, 'unit' => 'Fahrt', 'unit_price' => $price, 'tax_rate' => 19]]);

        if ($kind === 'angebot_entwurf') {
            return $count;
        }
        $offer = $this->documents->finalize($offer);
        if ($kind === 'angebot') {
            return $count;
        }

        $confirmation = $this->step($offer, DocumentType::Auftragsbestaetigung, $start->copy()->addDays(2));
        $count++;
        if ($kind === 'bestaetigt') {
            return $count;
        }

        $base = $confirmation;
        if ($kind === 'anzahlung_rechnung') {
            $advance = $this->step($confirmation, DocumentType::Anzahlung, $start->copy()->addDays(3), 30.0);
            $this->pay($advance, $start->copy()->addDays(8));
            $count++;
        }

        $invoice = $this->step($base, DocumentType::Rechnung, $start->copy()->addDays(5));
        $count++;

        match ($kind) {
            'rechnung_bezahlt', 'anzahlung_rechnung' => $this->pay($invoice, $start->copy()->addDays(12)),
            'rechnung_teilweise' => $this->pay($invoice, $start->copy()->addDays(14), 0.4),
            'storniert' => $count += $this->documents->cancel($invoice) ? 1 : 0,
            default => null,
        };

        return $count;
    }

    /** Folgebeleg mit Datum anlegen und festschreiben. */
    private function step(Document $source, DocumentType $type, Carbon $date, ?float $percent = null): Document
    {
        if (in_array($type, $source->type->convertibleTo(), true)) {
            $next = $this->documents->convert($source, $type, $percent);
        } else {
            // Die Rechnung entsteht in der Anwendung aus den Fahrtaufträgen (Etappe 4, noch nicht gebaut), nicht aus der Belegkette.
            // Für die Demo hängt sie mit Verweis und Positionen der Auftragsbestätigung in der Kette, damit die Liste sie zeigt.
            $source->loadMissing('lines');
            $next = $this->documents->createDraft($type, [
                'customer_id' => $source->customer_id,
                'subject' => $source->subject,
                'service_date_from' => $source->service_date_from,
            ], $source->lines->map(fn ($l) => [
                'title' => $l->title, 'description' => $l->description, 'quantity' => (string) $l->quantity, 'unit' => $l->unit,
                'unit_price' => (string) $l->unit_price, 'tax_rate' => (string) $l->tax_rate, 'tax_note' => $l->tax_note,
            ])->all());
            $next->forceFill(['source_id' => $source->id])->save();
        }
        $next->forceFill(['document_date' => $date->toDateString()])->save();

        return $this->documents->finalize($next);
    }

    /** Zahlung buchen: ganz, oder nur der Anteil $part (0 bis 1) des offenen Betrags; nie in der Zukunft. */
    private function pay(Document $document, Carbon $on, float $part = 1.0): void
    {
        $document = $document->fresh();
        $open = $this->payments->summary($document)['open'] ?? $document->payable_total;
        $amount = $part >= 1.0 ? (float) $open : round((float) $open * $part, 2);
        if ($amount <= 0) {
            return;
        }
        $this->payments->record($document, [
            'paid_on' => $on->greaterThan(Carbon::today()) ? Carbon::today()->toDateString() : $on->toDateString(),
            'amount' => number_format($amount, 2, '.', ''),
            'reference' => 'Demo-Zahlung',
        ]);
    }
}
