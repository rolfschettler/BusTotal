<?php

namespace App\Services;

use App\Enums\TripKind;
use App\Models\Document;
use App\Models\PricingSetting;

/**
 * Prüfhinweise zum Steuersatz der Beförderung. Sie WARNEN nur, sie sperren nie: Die Software kennt die Strecke einer
 * Position nicht und die Rechtslage hat Ausnahmen. Grundlage: § 12 Abs. 2 Nr. 10 UStG, UStAE 12.13 und 12.14
 * (docs/fachlich/befoerderung-ergebnisse.md, Frage 11).
 */
class TaxHints
{
    /**
     * @return list<string> Hinweise in Klartext, gruppiert (eine Meldung nennt alle betroffenen Positionen)
     */
    public function forDocument(Document $document): array
    {
        $regular = (float) PricingSetting::current()->tax_rate;
        $notReducedBySchedule = [];   // Gelegenheitsverkehr mit ermäßigtem Satz
        $reducedNeedsDistance = [];   // Linienverkehr oder Schülerfahrt mit ermäßigtem Satz

        foreach ($document->lines as $line) {
            $kind = $line->trip_kind;
            $rate = (float) $line->tax_rate;
            $reduced = $rate > 0 && $rate < $regular;

            if ($kind === TripKind::Gelegenheit && $reduced) {
                $notReducedBySchedule[] = $line->position;
            } elseif ($kind?->mayBeReduced() && $reduced) {
                $reducedNeedsDistance[] = $line->position;
            }
        }

        $hints = [];
        if ($notReducedBySchedule !== []) {
            $hints[] = $this->positions($notReducedBySchedule).': Gelegenheitsverkehr mit Omnibus (Charter, Ausflug, Gruppenfahrt, Mietbus) '
                .'ist nicht ermäßigt (§ 12 Abs. 2 Nr. 10 UStG). Bitte den Steuersatz prüfen.';
        }
        if ($reducedNeedsDistance !== []) {
            $hints[] = $this->positions($reducedNeedsDistance).': Der ermäßigte Satz gilt nur innerhalb einer Gemeinde oder bei einer '
                .'Beförderungsstrecke bis 50 km (bei Fahrten ins Ausland zählt nur der Inlandsteil). Bitte die Strecke prüfen.';
        }

        return $hints;
    }

    /** „Position 3“ oder „Positionen 1, 2 und 4“. */
    private function positions(array $numbers): string
    {
        if (count($numbers) === 1) {
            return 'Position '.$numbers[0];
        }
        $last = array_pop($numbers);

        return 'Positionen '.implode(', ', $numbers).' und '.$last;
    }
}
