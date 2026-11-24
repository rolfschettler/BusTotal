<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\TripOrder;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Fahrer-Fassung des Fahrtauftrags (A4): Strecke, Fahrzeug, Fahrer, Hinweise und danach ein Erfassungsblatt zum Ausfüllen unterwegs.
 * **Ohne Preise.** Das PDF entsteht bei jedem Abruf neu aus dem aktuellen Stand (Fahrzeug und Fahrer können sich bis zur Fahrt ändern);
 * die Strecke kommt aus dem Stand der Auftragsbestätigung, nicht aus dem Tour-Archiv.
 */
class TripOrderPdf
{
    public function render(TripOrder $order): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml($this->html($order), 'UTF-8');
        $pdf->render();
        DocumentPdf::addPageNumbers($pdf);

        return (string) $pdf->output();
    }

    /**
     * Vorbelegung der Tabelle „Zeiten und Kilometerstände“ im Erfassungsblatt: nur **Datum und Ort**, keine Uhrzeiten und keine
     * Kilometerstände (die trägt der Fahrer ein). Der Ort ist der Name vor dem ersten Komma („Mietingen, Landkreis Biberach …“ → „Mietingen“).
     * - Abfahrt erster Halt: Beginn der Fahrt (bei gesetzter Abfahrt deren Tag in deutscher Zeit), Ort = erster Halt.
     * - Ankunft Ziel: Ende der Fahrt, sonst Beginn plus die Tage der Tour; Ort = letzter Halt.
     * - Betriebshof: nur der Ort, und nur wenn die Tour eine Leerfahrt dorthin enthält. Ohne Angaben bleibt eine Zeile leer.
     *
     * @return list<array{event: string, date: ?string, place: string}>
     */
    public function eventRows(TripOrder $order): array
    {
        $route = $order->route ?? [];
        $trip = $route['trip'] ?? null;
        $stops = $trip['stops'] ?? [];
        $legs = $trip['legs'] ?? [];
        $place = fn (?string $label) => $label === null ? '' : trim(explode(',', $label)[0]);

        $from = $order->departure_at?->copy()->timezone('Europe/Berlin')->startOfDay() ?? $order->date_from?->copy();
        $days = max(1, (int) ($trip['days'] ?? $route['own']['days'] ?? 1));
        $to = $order->date_to?->copy() ?? $from?->copy()->addDays($days - 1);

        $first = $legs[0] ?? null;
        $last = $legs !== [] ? $legs[count($legs) - 1] : null;
        $depotOut = $first !== null && ! empty($first['empty']) ? $place($first['from'] ?? null) : '';
        $depotBack = $last !== null && ! empty($last['empty']) ? $place($last['to'] ?? null) : '';

        $firstStop = $place($stops[0]['label'] ?? null);
        $lastStop = $place($stops !== [] ? $stops[count($stops) - 1]['label'] ?? null : null);
        $fmt = fn ($d) => $d?->format('d.m.Y');

        return [
            ['event' => 'Abfahrt Betriebshof', 'date' => null, 'place' => $depotOut],
            ['event' => 'Abfahrt erster Halt', 'date' => $fmt($from), 'place' => $firstStop],
            ['event' => 'Ankunft Ziel', 'date' => $fmt($to), 'place' => $lastStop],
            ['event' => 'Abfahrt Rückreise', 'date' => null, 'place' => ''],
            ['event' => 'Ende am Betriebshof', 'date' => null, 'place' => $depotBack],
        ];
    }

    public function html(TripOrder $order): string
    {
        $order->load(['customer', 'vehicle', 'driver', 'driver2', 'section.journey', 'boardings']);
        $company = CompanySetting::current();
        $brand = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $company->brand_color) ? $company->brand_color : '#1d4e89';

        return view('pdf.trip-order', [
            'order' => $order,
            'events' => $this->eventRows($order),
            'company' => $company,
            'brand' => $brand,
            'brandOn' => DocumentPdf::textOn($brand),
            'logo' => DocumentPdf::logoDataUri($company),
        ])->render();
    }
}
