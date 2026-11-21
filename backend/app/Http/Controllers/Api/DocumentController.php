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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    /**
     * Sortierbare Spalten der Liste: Die Anfrage wählt nur einen Eintrag aus dieser festen Liste, nie einen Spaltennamen
     * (siehe docs/ClaudeCodePatterns/TableViewPattern.md). `date` ist Sonderfall, siehe orderByColumn().
     */
    private const SORTS = [
        'number' => 'documents.number',
        // Reihenfolge des Geschäftsgangs statt Alphabet: Angebot → Auftragsbestätigung → Anzahlung → Rechnung → Gutschrift
        'type' => "FIELD(documents.type, 'angebot', 'auftragsbestaetigung', 'anzahlung', 'rechnung', 'gutschrift')",
        'customer' => "COALESCE(NULLIF(c.company_name, ''), c.last_name, c.customer_number)",
        'date' => 'documents.document_date',
        'gross' => 'documents.gross_total',
        'status' => 'documents.status',
    ];

    public function __construct(private readonly DocumentService $documents) {}

    /** Liste mit Filter nach Art, Status und Kunde, Suche nach Nummer und Kunde, Seiten à 20. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(DocumentType::class)],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'customer_id' => ['nullable', 'uuid'],
            'locate' => ['nullable', 'uuid'],
            'order' => ['nullable', Rule::in(['column', 'chain'])],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Document::query()
            ->with(['customer:id,customer_number,company_name,first_name,last_name', 'source:id,type,number'])
            ->select('documents.*')
            ->leftJoin('customers as c', 'c.id', '=', 'documents.customer_id');

        foreach (['type', 'status', 'customer_id'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where('documents.'.$filter, $value);
            }
        }

        if ($q = trim((string) $request->query('q'))) {
            // % und _ sind in LIKE Platzhalter – hier wörtlich gemeint.
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($w) use ($like) {
                $w->where('documents.number', 'like', $like)
                    ->orWhere('documents.subject', 'like', $like)
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

        if ($request->query('order') === 'chain') {
            $this->orderByCustomerChain($query);
        } else {
            $this->orderByColumn($query, $request->query('sort') ?: 'date', $request->query('dir') === 'asc' ? 'asc' : 'desc');
        }

        // locate: die Seite, auf der dieser Beleg bei der gewählten Suche, Filterung und Sortierung steht (Rücksprung aus einem
        // Beleg). Steht er nicht in der Liste, gilt die angefragte Seite.
        $page = max(1, (int) $request->query('page', 1));
        if ($locate = $request->query('locate')) {
            $position = (clone $query)->select('documents.id')->pluck('id')->search($locate);
            if ($position !== false) {
                $page = intdiv($position, 20) + 1;
            }
        }

        return response()->json($query->paginate(20, ['*'], 'page', $page)->withQueryString());
    }

    /**
     * Standard: Datum, neueste zuerst. Leere Werte stehen am Ende, nur beim Datum nicht: Ein Entwurf hat noch kein Datum
     * und gilt als der neueste Beleg (steht bei „absteigend“ oben, bei „aufsteigend“ unten). Bei Gleichstand entscheiden
     * Anlegezeit und ID, sonst könnte ein Beleg beim Blättern doppelt erscheinen oder fehlen.
     */
    private function orderByColumn($query, string $sort, string $dir): void
    {
        $expression = self::SORTS[$sort];

        if ($sort === 'date') {
            $query->orderByRaw('documents.document_date IS NULL '.($dir === 'desc' ? 'DESC' : 'ASC'));
        } else {
            $query->orderByRaw("({$expression}) IS NULL");
        }

        $query->orderByRaw("{$expression} {$dir}")
            ->orderByDesc('documents.created_at')
            ->orderByDesc('documents.id');
    }

    /**
     * Je Kunde untereinander, darin die Belegketten (Angebot → Auftragsbestätigung → Rechnung → Storno) in der Reihenfolge
     * ihrer Entstehung. Die Kette folgt `source_id` rekursiv bis zum Ursprungsbeleg; `chain_depth` ist die Stufe darin
     * (0 = Ursprung). Die ID entscheidet bei Gleichstand (stabiles Blättern).
     */
    private function orderByCustomerChain($query): void
    {
        $chain = <<<'SQL'
            (WITH RECURSIVE chain (id, root_id, depth) AS (
                SELECT id, id, 0 FROM documents WHERE source_id IS NULL
                UNION ALL
                SELECT d.id, c.root_id, c.depth + 1 FROM documents d JOIN chain c ON d.source_id = c.id
            ) SELECT id, root_id, depth FROM chain)
            SQL;

        $query->addSelect('chain.depth as chain_depth')
            ->join(DB::raw($chain.' AS chain'), 'chain.id', '=', 'documents.id')
            ->join('documents as root', 'root.id', '=', 'chain.root_id')
            ->orderByRaw('c.id IS NULL')
            ->orderByRaw("COALESCE(NULLIF(c.company_name, ''), c.last_name, c.customer_number)")
            ->orderBy('c.id')
            ->orderBy('root.created_at')
            ->orderBy('root.id')
            ->orderBy('documents.created_at')
            ->orderBy('documents.id');
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
