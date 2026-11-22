<?php

namespace App\Enums;

/**
 * Fahrtart einer Belegposition. Sie bestimmt den Steuersatz der Beförderung (§ 12 Abs. 2 Nr. 10 UStG):
 *
 *  - Gelegenheitsverkehr mit Omnibus (Charter, Ausflug, Gruppenfahrt, Mietbus mit Fahrer): Regelsatz, nicht ermäßigt.
 *  - Genehmigter Linienverkehr und Schülerfahrten (Wohnung–Schule, Schulträger): ermäßigter Satz, aber nur innerhalb
 *    einer Gemeinde oder bei einer Beförderungsstrecke bis 50 km (UStAE 12.13, 12.14).
 *  - Sonstiges: alles andere (Maut, Zuschläge, Übernachtung); der Satz wird von Hand gewählt.
 *
 * Grundlage: docs/fachlich/befoerderung-ergebnisse.md, Entscheidung in docs/fachlich/loesungskonzept-abrechnung.md Abschnitt 2.1.
 */
enum TripKind: string
{
    case Gelegenheit = 'gelegenheit';
    case Linie = 'linie';
    case Schule = 'schule';
    case Sonstig = 'sonstig';

    public function label(): string
    {
        return match ($this) {
            // Kurz gehalten: Die Auswahl steht in einem Drittel der Formularbreite. Was dazugehört, erklärt die Hilfe.
            self::Gelegenheit => 'Gelegenheitsverkehr',
            self::Linie => 'Linienverkehr (genehmigt)',
            self::Schule => 'Schüler-/Schulträgerfahrt',
            self::Sonstig => 'Sonstiges',
        };
    }

    /** Kann für diese Fahrtart der ermäßigte Steuersatz gelten (nur bis 50 km)? */
    public function mayBeReduced(): bool
    {
        return $this === self::Linie || $this === self::Schule;
    }

    /** Auswahlliste für die Oberfläche. */
    public static function options(): array
    {
        return array_map(fn (self $k) => ['value' => $k->value, 'label' => $k->label(), 'may_be_reduced' => $k->mayBeReduced()], self::cases());
    }
}
