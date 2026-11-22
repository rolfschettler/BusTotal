<?php

namespace App\Pricing;

use App\Models\PricingSetting;
use App\Services\DocumentCalculator;

/**
 * Standard-Preisfindung mit den Preisen aus den Firmen-Einstellungen. Jeder Bestandteil mit Preis 0 entfällt.
 *
 *   Kilometer      (Fahrt-km + Leer-km × Leer-Prozent) × Preis je km
 *   Fahrerzeit     Lenkstunden × Stundensatz
 *   Tagespauschale Einsatztage × Pauschale
 *   Übernachtung   Nächte × Satz
 *   Mindestpreis   fehlt die Summe zum Mindestpreis, kommt eine Ausgleichsposition dazu
 *   Tageszuschlag  Samstag, Sonntag, Feiertag (automatisch aus den Kalendertagen): Satz auf den Tagesanteil der Summe
 *   Zuschlag       Prozent auf die Summe (von Hand gewählt)
 *
 * Je Fahrzeugtyp können Preis je km, Stundensatz, Tagespauschale, Übernachtung und Mindestpreis abweichen
 * (PricingVehicleRate). Ein leeres Feld beim Typ übernimmt den Standardpreis.
 *
 * Das ist eine Vorlage zum Ausprobieren, nicht das endgültige Kalkulationsschema. Maut fehlt bewusst (kein Dienst liefert sie):
 * Sie kommt als Position von Hand dazu.
 */
class StandardPriceCalculator implements PriceCalculator
{
    public function __construct(private readonly DocumentCalculator $math) {}

    public function quote(TourFigures $tour): array
    {
        $settings = PricingSetting::current();
        // Der Steuersatz folgt der Fahrtart (Regelsatz, Linienverkehr, Schülerfahrt), siehe PricingSetting::taxRateFor().
        $rate = $settings->taxRateFor($tour->tripKind);
        $lines = [];

        // Preise des Fahrzeugtyps, wo ausgefüllt, sonst die Standardpreise. Leerfahrt-Prozent und Steuer gelten für alle.
        $p = (object) ($settings->ratesFor($tour->vehicleType) + ['empty_km_percent' => $settings->empty_km_percent]);

        $billedKm = round($tour->loadedKm + $tour->emptyKm * $p->empty_km_percent / 100, 1);
        if ((float) $p->price_per_km > 0 && $billedKm > 0) {
            $note = $tour->emptyKm > 0
                ? sprintf('%s km mit Fahrgästen, %s km Leerfahrt zu %d %%', $this->num($tour->loadedKm), $this->num($tour->emptyKm), $p->empty_km_percent)
                : null;
            $lines[] = $this->line('Fahrstrecke', $note, $billedKm, 'km', $p->price_per_km, $rate);
        }

        $hours = round($tour->driveSeconds / 3600, 2);
        if ((float) $p->hourly_rate > 0 && $hours > 0) {
            $lines[] = $this->line('Fahrerzeit', 'Lenkzeit laut Planung', $hours, 'Std', $p->hourly_rate, $rate);
        }

        if ((float) $p->day_rate > 0 && $tour->days > 0) {
            $lines[] = $this->line('Tagespauschale', null, $tour->days, 'Tag', $p->day_rate, $rate);
        }

        if ((float) $p->overnight_rate > 0 && $tour->nights() > 0) {
            $lines[] = $this->line('Übernachtung Fahrer', null, $tour->nights(), 'Nacht', $p->overnight_rate, $rate);
        }

        // Mindestpreis: nur auffüllen, nie kürzen.
        $netCents = $this->sumCents($lines);
        $minCents = (int) round(((float) $p->minimum_price) * 100);
        if ($minCents > 0 && $lines !== [] && $netCents < $minCents) {
            $lines[] = $this->line('Mindestpreis-Ausgleich', 'Auf den Mindestpreis von '.$this->money($minCents).' aufgefüllt', 1, 'pauschal', $this->cents($minCents - $netCents), $rate);
        } elseif ($minCents > 0 && $lines === []) {
            $lines[] = $this->line('Pauschalpreis', 'Mindestpreis', 1, 'pauschal', $p->minimum_price, $rate);
        }

        // Beide Zuschläge rechnen auf die Summe vor den Zuschlägen, damit sie sich nicht gegenseitig aufschaukeln.
        $base = $this->sumCents($lines);

        if ($lines !== []) {
            array_push($lines, ...$this->daySurcharges($tour, $settings, $base, $rate));
        }

        if ($tour->surchargePercent > 0 && $lines !== []) {
            $surcharge = intdiv($base * (int) round($tour->surchargePercent * 100) + 5000, 10000);
            $lines[] = $this->line('Zuschlag '.$this->num($tour->surchargePercent).' %', null, 1, 'pauschal', $this->cents($surcharge), $rate);
        }

        // Alle Positionen des Angebots tragen die Fahrtart der Tour (Zuschläge folgen der Hauptleistung).
        return array_map(fn (array $l) => $l + ['trip_kind' => $tour->tripKind?->value], $lines);
    }

    /**
     * Zuschläge für Samstag, Sonntag und Feiertag. Die Summe wird gleichmäßig auf die Kalendertage verteilt, jeder betroffene Tag
     * bekommt den Satz auf seinen Anteil. Trifft mehr als ein Satz auf einen Tag zu (Feiertag auf einen Sonntag), gilt nur der höchste.
     *
     * @return list<array>
     */
    private function daySurcharges(TourFigures $tour, PricingSetting $p, int $baseCents, string $taxRate): array
    {
        $days = count($tour->calendarDays);
        if ($days === 0 || $baseCents <= 0) {
            return [];
        }

        // Je Art: Satz in Hundertstel Prozent und die betroffenen Tage (Bezeichnung)
        $kinds = [
            'Feiertag' => ['rate' => (int) round((float) $p->surcharge_holiday_percent * 100), 'days' => []],
            'Sonntag' => ['rate' => (int) round((float) $p->surcharge_sunday_percent * 100), 'days' => []],
            'Samstag' => ['rate' => (int) round((float) $p->surcharge_saturday_percent * 100), 'days' => []],
        ];

        foreach ($tour->calendarDays as $day) {
            $short = date('d.m.', strtotime($day->date));
            $applies = array_filter([
                'Feiertag' => $day->holiday !== null ? "{$day->holiday} $short" : null,
                'Sonntag' => $day->sunday ? "So $short" : null,
                'Samstag' => $day->saturday ? "Sa $short" : null,
            ], fn ($label) => $label !== null);

            $best = null;
            foreach ($applies as $kind => $label) {
                if ($kinds[$kind]['rate'] > 0 && ($best === null || $kinds[$kind]['rate'] > $kinds[$best]['rate'])) {
                    $best = $kind;   // bei gleichem Satz gewinnt die frühere Art (Feiertag vor Sonntag vor Samstag)
                }
            }
            if ($best !== null) {
                $kinds[$best]['days'][] = $applies[$best];
            }
        }

        $lines = [];
        foreach ($kinds as $kind => $info) {
            $count = count($info['days']);
            if ($count === 0) {
                continue;
            }
            // Summe × betroffene Tage / Kalendertage × Satz, kaufmännisch auf ganze Cent gerundet
            $den = $days * 10000;
            $cents = intdiv(2 * $baseCents * $count * $info['rate'] + $den, 2 * $den);
            if ($cents <= 0) {
                continue;
            }
            $lines[] = $this->line(
                'Zuschlag '.$kind,
                sprintf('%d von %d Tagen (%s), %s %% auf den Tagesanteil', $count, $days, implode(', ', $info['days']), $this->num($info['rate'] / 100)),
                1, 'pauschal', $this->cents($cents), $taxRate,
            );
        }

        return $lines;
    }

    private function line(string $title, ?string $description, float|int $qty, string $unit, string $unitPrice, string $taxRate): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'quantity' => (string) $qty,
            'unit' => $unit,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'tax_note' => null,
        ];
    }

    private function sumCents(array $lines): int
    {
        return array_sum(array_map(fn ($l) => $this->math->lineNetCents($l['quantity'], $l['unit_price']), $lines));
    }

    private function cents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, ',', '.'), '0'), ',');
    }
}
