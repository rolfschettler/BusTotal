<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Document;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/**
 * Erzeugt das PDF eines Belegs (A4, Geschäftsbrief mit Briefkopf, Positionen, Summen, Zahlungsangaben, Fußzeile).
 *
 * Festgeschriebene Belege zeigen Absender und Empfänger aus der Kopie, die beim Festschreiben gespeichert wurde, nie die
 * heutigen Stammdaten. Ein Entwurf folgt den Stammdaten und trägt den Hinweis „ENTWURF“.
 * Das PDF wird bei jedem Abruf neu erzeugt und nicht gespeichert. (Offen: Archiv-PDF beim Festschreiben, siehe Fahrplan.)
 */
class DocumentPdf
{
    public function __construct(private readonly DocumentService $documents) {}

    public function render(Document $document): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);   // nie fremde Adressen laden
        $options->set('isPhpEnabled', false);      // kein PHP in der Vorlage ausführen
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml($this->html($document), 'UTF-8');
        $pdf->render();

        return (string) $pdf->output();
    }

    /** Das HTML, aus dem das PDF entsteht (getrennt, damit es sich ohne PDF-Lesegerät prüfen lässt). */
    public function html(Document $document): string
    {
        $document->load(['lines', 'customer', 'source:id,type,number', 'cancelledBy:id,number']);
        $company = CompanySetting::current();
        $isDraft = $document->isDraft();

        // Ältere Kopien kennen Briefkopf-Zusatz und Fußzeile noch nicht: dann gelten die heutigen Werte.
        $seller = $document->seller
            ?? $this->documents->sellerSnapshot($company);
        foreach (['letterhead_note', 'footer_text'] as $key) {
            if (! array_key_exists($key, $seller)) {
                $seller[$key] = $company->{$key};
            }
        }

        $recipient = $document->recipient
            ?? ($document->customer ? $this->documents->recipientSnapshot($document->customer) : ['name' => '']);

        $brand = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $company->brand_color) ? $company->brand_color : '#1d4e89';

        return view('pdf.document', [
            'doc' => $document,
            'type' => $document->type,
            'isDraft' => $isDraft,
            'seller' => $seller,
            'recipient' => $recipient,
            'lines' => $document->lines,
            'brand' => $brand,
            'brandOn' => $this->textOn($brand),
            'logo' => $this->logoDataUri($company),
        ])->render();
    }

    /** Das Logo als eingebettetes Bild. Es kommt aus dem privaten Speicher, nie aus dem Netz. */
    private function logoDataUri(CompanySetting $company): ?string
    {
        $path = $company->logo_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        return in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)
            ? 'data:'.$mime.';base64,'.base64_encode($bytes)
            : null;
    }

    /** Schwarz oder Weiß auf der Markenfarbe, je nach Helligkeit (wie im Frontend). */
    private function textOn(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $luma = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luma > 0.6 ? '#000000' : '#ffffff';
    }
}
