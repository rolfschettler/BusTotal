<?php

namespace App\Services;

use App\Models\Document;

/**
 * Rechnet Positionen und Summen eines Belegs. Alles in ganzen Cent (Ganzzahlen), nie mit Kommazahlen:
 * So gibt es keine Rundungsfehler wie 1,005 → 1,00.
 *
 * Regel: Die Steuer wird je Steuersatz aus der Summe der Netto-Beträge berechnet (nicht je Position).
 * Das ist die übliche Darstellung auf Rechnungen (Netto, Steuer je Satz, Brutto).
 * [offen, Steuerberater bestätigen lassen]
 */
class DocumentCalculator
{
    /** Positionen und Summen berechnen und speichern. Nur für Entwürfe aufrufen. */
    public function apply(Document $document): void
    {
        $document->load('lines');

        $groups = []; // Steuersatz (in 1/100 %) => Netto in Cent
        foreach ($document->lines as $line) {
            $cents = $this->lineNetCents((string) $line->quantity, (string) $line->unit_price);
            $line->net_amount = $this->money($cents);
            $line->save();

            $rate = (int) round(((float) $line->tax_rate) * 100);
            $groups[$rate] = ($groups[$rate] ?? 0) + $cents;
        }

        krsort($groups);
        $summary = [];
        $net = 0;
        $tax = 0;
        foreach ($groups as $rate => $groupNet) {
            $groupTax = $this->roundDiv($groupNet * $rate, 10000);
            $summary[] = [
                'rate' => number_format($rate / 100, 2, '.', ''),
                'net' => $this->money($groupNet),
                'tax' => $this->money($groupTax),
            ];
            $net += $groupNet;
            $tax += $groupTax;
        }

        // Abzug erhaltener Anzahlungen (Schlussrechnung): getrennt ausgewiesen, nicht in den Positionen.
        $dedNet = 0;
        $dedTax = 0;
        $dedGross = 0;
        foreach ($document->deductions ?? [] as $d) {
            $dedNet += $this->toCents($d['net'] ?? 0);
            $dedTax += $this->toCents($d['tax'] ?? 0);
            $dedGross += $this->toCents($d['gross'] ?? 0);
        }

        $document->forceFill([
            'net_total' => $this->money($net),
            'tax_total' => $this->money($tax),
            'gross_total' => $this->money($net + $tax),
            'tax_summary' => $summary,
            'deduction_net' => $this->money($dedNet),
            'deduction_tax' => $this->money($dedTax),
            'deduction_gross' => $this->money($dedGross),
            'payable_total' => $this->money($net + $tax - $dedGross),
        ])->save();
    }

    private function toCents(string|int|float|null $amount): int
    {
        $x = (float) $amount * 100;

        return (int) ($x < 0 ? -round(-$x) : round($x));
    }

    /** Menge (3 Nachkommastellen) × Einzelpreis (2 Nachkommastellen) in Cent, kaufmännisch gerundet. */
    public function lineNetCents(string $quantity, string $unitPrice): int
    {
        $milli = (int) round(((float) $quantity) * 1000);
        $cents = (int) round(((float) $unitPrice) * 100);

        return $this->roundDiv($milli * $cents, 1000);
    }

    /** Ganzzahl-Division mit Rundung „halb weg von null“ (auch für negative Beträge, z. B. Storno). */
    private function roundDiv(int $value, int $divisor): int
    {
        $half = intdiv($divisor, 2);

        return $value >= 0
            ? intdiv($value + $half, $divisor)
            : -intdiv(-$value + $half, $divisor);
    }

    private function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }
}
