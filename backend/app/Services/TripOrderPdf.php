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

    public function html(TripOrder $order): string
    {
        $order->load(['customer', 'vehicle', 'driver', 'driver2', 'section.journey']);
        $company = CompanySetting::current();
        $brand = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $company->brand_color) ? $company->brand_color : '#1d4e89';

        return view('pdf.trip-order', [
            'order' => $order,
            'company' => $company,
            'brand' => $brand,
            'brandOn' => DocumentPdf::textOn($brand),
            'logo' => DocumentPdf::logoDataUri($company),
        ])->render();
    }
}
