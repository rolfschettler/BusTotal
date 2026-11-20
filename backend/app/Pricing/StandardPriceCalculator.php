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
        $rate = (string) $settings->tax_rate;
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

        if ($tour->surchargePercent > 0 && $lines !== []) {
            $base = $this->sumCents($lines);
            $surcharge = intdiv($base * (int) round($tour->surchargePercent * 100) + 5000, 10000);
            $lines[] = $this->line('Zuschlag '.$this->num($tour->surchargePercent).' %', null, 1, 'pauschal', $this->cents($surcharge), $rate);
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
