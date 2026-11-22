<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Document;
use App\Models\DocumentPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zahlungseingang und Rückzahlung zu Rechnungen und Anzahlungsrechnungen.
 *
 * Der festgeschriebene Beleg bleibt unverändert (GoBD). Sein Zahlstand ergibt sich aus den Buchungen:
 * bezahlt = Summe der Zahlungseingänge minus Rückzahlungen, ohne stornierte Buchungen. Gerechnet wird in ganzen Cent.
 * Grundlage: Die Steuer auf Anzahlungen und ihre Berichtigung hängen am tatsächlichen Zahlungsfluss
 * (§ 13 UStG; UStAE 13.5 und 17.1), siehe docs/fachlich/rechnungen-ergebnisse.md Fragen 16 und 17.
 */
class PaymentService
{
    /** Zu welchen Belegen gibt es Zahlungen: festgeschriebene Rechnungen und Anzahlungsrechnungen (kein Storno-Beleg, kein Angebot). */
    public function applies(Document $document): bool
    {
        return ! $document->isDraft() && in_array($document->type, [DocumentType::Rechnung, DocumentType::Anzahlung], true);
    }

    /**
     * Zahlstand eines Belegs, oder null, wenn es dazu keine Zahlungen gibt.
     * `$paidCents` spart die Abfrage, wenn die Summen für eine ganze Liste schon vorliegen.
     *
     * @return array{payable: string, paid: string, open: string, state: string, overdue: bool}|null
     */
    public function summary(Document $document, ?int $paidCents = null): ?array
    {
        if (! $this->applies($document)) {
            return null;
        }

        $payable = $this->cents($document->payable_total);
        $paid = $paidCents ?? $this->paidCents($document);
        $open = max(0, $payable - $paid);
        $cancelled = $document->cancelled_by_id !== null;

        $state = match (true) {
            $cancelled => 'storniert',
            $open === 0 => 'bezahlt',
            $paid > 0 => 'teilweise',
            default => 'offen',
        };

        return [
            'payable' => $this->money($payable),
            'paid' => $this->money($paid),
            'open' => $this->money($cancelled ? 0 : $open),
            'state' => $state,
            // überfällig: noch etwas offen und die Fälligkeit liegt vor heute
            'overdue' => ! $cancelled && $open > 0 && $document->due_date !== null && $document->due_date->lt(Carbon::today()),
        ];
    }

    /** Bezahlter Betrag in Cent: Zahlungseingänge minus Rückzahlungen, ohne stornierte Buchungen. */
    public function paidCents(Document $document): int
    {
        return $this->paidTotals([$document->id])[$document->id] ?? 0;
    }

    /**
     * Bezahlte Beträge (Cent) für mehrere Belege mit EINER Abfrage, damit die Liste nicht je Zeile fragt.
     *
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    public function paidTotals(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DocumentPayment::query()
            ->whereIn('document_id', $ids)
            ->whereNull('cancelled_at')
            ->selectRaw("document_id, SUM(CASE WHEN kind = 'eingang' THEN amount ELSE -amount END) as paid")
            ->groupBy('document_id')
            ->pluck('paid', 'document_id')
            ->map(fn ($v) => $this->cents((string) $v))
            ->all();
    }

    /**
     * Zahlung buchen.
     *
     * @param  array{kind?: string, paid_on: string, amount: string|float|int, method?: ?string, reference?: ?string, note?: ?string}  $data
     */
    public function record(Document $document, array $data): DocumentPayment
    {
        return DB::transaction(function () use ($document, $data) {
            // Zeilensperre: zwei gleichzeitige Buchungen dürfen den offenen Betrag nicht gemeinsam überschreiten.
            $locked = Document::lockForUpdate()->findOrFail($document->id);

            if (! $this->applies($locked)) {
                $this->fail('amount', 'Zahlungen gibt es nur zu festgeschriebenen Rechnungen und Anzahlungsrechnungen.');
            }

            $kind = PaymentKind::from($data['kind'] ?? PaymentKind::Eingang->value);
            $cents = $this->cents((string) $data['amount']);
            if ($cents <= 0) {
                $this->fail('amount', 'Der Betrag muss größer als 0 sein.');
            }
            if (Carbon::parse($data['paid_on'])->gt(Carbon::today())) {
                $this->fail('paid_on', 'Das Zahlungsdatum darf nicht in der Zukunft liegen.');
            }

            $paid = $this->paidCents($locked);
            if ($kind === PaymentKind::Eingang) {
                if ($locked->cancelled_by_id !== null) {
                    $this->fail('amount', 'Dieser Beleg ist storniert. Ein Zahlungseingang kann nicht mehr gebucht werden, nur eine Rückzahlung.');
                }
                $open = $this->cents($locked->payable_total) - $paid;
                if ($open <= 0) {
                    $this->fail('amount', 'Dieser Beleg ist bereits vollständig bezahlt.');
                }
                if ($cents > $open) {
                    $this->fail('amount', 'Der Betrag ist höher als der offene Betrag von '.$this->display($open).'.');
                }
            } elseif ($cents > $paid) {
                $this->fail('amount', 'Es wurden erst '.$this->display(max(0, $paid)).' bezahlt. Mehr kann nicht zurückgezahlt werden.');
            }

            return DocumentPayment::create([
                'document_id' => $locked->id,
                'kind' => $kind,
                'paid_on' => $data['paid_on'],
                'amount' => $this->money($cents),
                'method' => PaymentMethod::tryFrom((string) ($data['method'] ?? ''))?->value ?? PaymentMethod::Ueberweisung->value,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /** Buchung stornieren (nie löschen). Der Grund ist Pflicht und bleibt am Datensatz. */
    public function cancel(DocumentPayment $payment, string $reason): DocumentPayment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $locked = DocumentPayment::lockForUpdate()->findOrFail($payment->id);
            if ($locked->isCancelled()) {
                $this->fail('reason', 'Diese Buchung ist bereits storniert.');
            }
            if (trim($reason) === '') {
                $this->fail('reason', 'Bitte einen Grund angeben.');
            }

            // Würde die Stornierung einer Rückzahlung den bezahlten Betrag über den Rechnungsbetrag heben, ginge das nicht auf.
            $document = Document::lockForUpdate()->findOrFail($locked->document_id);
            if ($locked->kind === PaymentKind::Rueckzahlung) {
                $after = $this->paidCents($document) + $this->cents((string) $locked->amount);
                if ($after > $this->cents($document->payable_total)) {
                    $this->fail('reason', 'Diese Rückzahlung lässt sich nicht stornieren: Sonst wäre mehr bezahlt als berechnet.');
                }
            }

            $locked->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => mb_substr(trim($reason), 0, 255),
            ])->save();

            return $locked;
        });
    }

    // ---------- intern ----------

    private function cents(string|int|float|null $amount): int
    {
        $x = (float) $amount * 100;

        return (int) ($x < 0 ? -round(-$x) : round($x));
    }

    private function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    /** „1.190,00 €“ für Meldungen. */
    private function display(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
