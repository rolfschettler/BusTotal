<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Services\DocumentArchive;
use App\Services\DocumentPdf;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    /** Liste mit Filter nach Art, Status und Kunde, Suche nach Nummer und Kunde, Seiten à 20. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(DocumentType::class)],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'customer_id' => ['nullable', 'uuid'],
        ]);

        $query = Document::query()->with('customer:id,customer_number,company_name,first_name,last_name');

        foreach (['type', 'status', 'customer_id'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }

        if ($q = trim((string) $request->query('q'))) {
            // % und _ sind in LIKE Platzhalter – hier wörtlich gemeint.
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($w) use ($like) {
                $w->where('number', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    // Die Spalten in eine eigene Klammer: Ein orWhere direkt in whereHas würde die Bedingung
                    // "gehört zu diesem Beleg" aushebeln, und jeder Beleg träfe, sobald irgendein Kunde passt.
                    ->orWhereHas('customer', function ($c) use ($like) {
                        $c->where(function ($w) use ($like) {
                            foreach (['company_name', 'first_name', 'last_name', 'customer_number'] as $col) {
                                $w->orWhere($col, 'like', $like);
                            }
                        });
                    });
            });
        }

        // Neueste zuerst; Entwürfe (ohne Datum) vor datierten Belegen. Die ID entscheidet bei Gleichstand,
        // sonst könnte ein Beleg beim Blättern doppelt erscheinen oder fehlen.
        $page = $query
            ->orderByRaw('document_date IS NULL DESC')
            ->orderByDesc('document_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return response()->json($page);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json($document->load(['lines', 'customer', 'source:id,type,number', 'cancelledBy:id,number']));
    }

    public function store(DocumentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $document = $this->documents->createDraft(DocumentType::from($data['type']), $data, $data['lines']);

        return response()->json($document, 201);
    }

    public function update(DocumentRequest $request, Document $document): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->documents->updateDraft($document, $data, $data['lines']));
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->documents->deleteDraft($document);

        return response()->json(['message' => 'Entwurf gelöscht.']);
    }

    /** Festschreiben: vergibt die Nummer und sperrt den Beleg. */
    public function finalize(Document $document): JsonResponse
    {
        return response()->json($this->documents->finalize($document)->load(['lines', 'customer']));
    }

    /** Stornieren: erzeugt den Storno-Beleg (Gutschrift) und gibt ihn zurück. */
    public function cancel(Document $document): JsonResponse
    {
        return response()->json($this->documents->cancel($document), 201);
    }

    /** Folgebeleg als Entwurf (Angebot → Auftragsbestätigung → Rechnung). */
    public function convert(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)],
            // Nur für die Anzahlungsrechnung: Anteil am Auftrag in Prozent (Vorbelegung 30).
            'percent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ]);

        return response()->json($this->documents->convert(
            $document,
            DocumentType::from($data['type']),
            isset($data['percent']) ? (float) $data['percent'] : null,
        ), 201);
    }

    /**
     * PDF des Belegs. Ein Entwurf wird frisch erzeugt und trägt den Hinweis „ENTWURF“. Ein festgeschriebener Beleg
     * liefert das beim Festschreiben abgelegte Archiv-PDF unverändert (nach Prüfsumme), nie ein neu erzeugtes.
     * Der Abruf steht im Änderungsprotokoll.
     */
    public function pdf(Document $document, DocumentPdf $pdf, DocumentArchive $archive): Response|JsonResponse
    {
        if ($document->isDraft()) {
            $binary = $pdf->render($document);
        } else {
            try {
                $binary = $archive->read($document);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage().' Bitte die Verwaltung informieren, die Sicherung enthält die Datei.'], 500);
            }
        }
        AuditLog::record('pdf_created', $document, null, ['number' => $document->number, 'status' => $document->status->value]);

        $name = ($document->number ?? 'Entwurf-'.$document->type->value).'.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Auswahlliste für Formulare. */
    public function types(): JsonResponse
    {
        return response()->json(collect(DocumentType::cases())->map(fn (DocumentType $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'invoice' => $t->isInvoice(),
            'convertible_to' => collect($t->convertibleTo())->map->value->values(),
        ]));
    }
}
