<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Archiv-PDF festgeschriebener Belege (GoBD).
 *
 * Das PDF entsteht einmal, beim Festschreiben (bzw. beim Storno), wird im privaten Speicher abgelegt
 * (Disk „local“, je Mandant, nie öffentlich) und danach unverändert ausgeliefert, nie neu erzeugt.
 * Eine SHA-256-Prüfsumme in der Tabelle `documents` erkennt Beschädigung oder Austausch der Datei.
 * Die Datei wird vom Programm nie gelöscht (Aufbewahrungsfrist); die Anonymisierung eines Kunden berührt sie nicht.
 */
class DocumentArchive
{
    /**
     * DocumentPdf wird erst bei Bedarf geholt: Es braucht den DocumentService (Absender-/Empfängerkopie), der selbst
     * dieses Archiv braucht. Ein Konstruktor-Parameter würde eine Endlosschleife beim Aufbau der Dienste ergeben.
     */
    private function pdf(): DocumentPdf
    {
        return app(DocumentPdf::class);
    }

    public function pathFor(Document $document): string
    {
        return 'archive/documents/'.$document->id.'.pdf';
    }

    /**
     * Erzeugt das PDF eines gerade festgeschriebenen Belegs (im Speicher, noch nicht gespeichert), legt es ab und setzt
     * die Archiv-Felder am Modell. Der Aufrufer speichert den Beleg. Wirft bei jedem Fehler, damit die Transaktion
     * des Festschreibens zurückgerollt wird: dann bleibt der Entwurf und keine Nummer ist verbraucht.
     */
    public function store(Document $document): void
    {
        $binary = $this->pdf()->render($document);
        if (! str_starts_with($binary, '%PDF-')) {
            throw new RuntimeException('Das Archiv-PDF konnte nicht erzeugt werden.');
        }

        $path = $this->pathFor($document);
        if (! Storage::disk('local')->put($path, $binary)) {
            throw new RuntimeException('Das Archiv-PDF konnte nicht abgelegt werden.');
        }

        $document->forceFill([
            'archive_path' => $path,
            'archive_sha256' => hash('sha256', $binary),
            'archived_at' => now(),
        ]);
    }

    /** Räumt eine Datei auf, wenn das Festschreiben nach dem Ablegen noch scheitert (die Datei gehört dann zu keinem Beleg). */
    public function discard(Document $document): void
    {
        Storage::disk('local')->delete($this->pathFor($document));
    }
    // Die E-Rechnung (XML) hat ihr eigenes Archiv: siehe EInvoiceService.

    /**
     * Die abgelegte Datei, nach Prüfung der Prüfsumme. Ältere festgeschriebene Belege ohne Archiv-PDF
     * (aus der Zeit davor) werden beim ersten Abruf einmalig nacherzeugt und danach genauso behandelt.
     * Logo und Markenfarbe stammen dann aus den heutigen Einstellungen; der Zeitpunkt steht in `archived_at`
     * und im Änderungsprotokoll (`archive_backfilled`).
     *
     * @throws RuntimeException wenn die Datei fehlt oder nicht zur Prüfsumme passt
     */
    public function read(Document $document): string
    {
        if ($document->archive_path === null) {
            $this->backfill($document);
        }

        $disk = Storage::disk('local');
        $binary = $disk->exists($document->archive_path) ? $disk->get($document->archive_path) : null;

        if ($binary === null || ! hash_equals((string) $document->archive_sha256, hash('sha256', $binary))) {
            AuditLog::record('archive_check_failed', $document, null, ['number' => $document->number, 'missing' => $binary === null]);
            throw new RuntimeException($binary === null
                ? 'Das Archiv-PDF dieses Belegs fehlt im Speicher.'
                : 'Das Archiv-PDF dieses Belegs stimmt nicht mehr mit seiner Prüfsumme überein.');
        }

        return $binary;
    }

    private function backfill(Document $document): void
    {
        $this->store($document);
        $document->save();
        AuditLog::record('archive_backfilled', $document, null, ['number' => $document->number]);
    }
}
