<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\SectionKind;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\JourneySection;
use App\Models\Trip;
use App\Services\DocumentArchive;
use App\Services\DocumentPdf;
use App\Services\DocumentService;
use App\Services\EInvoiceService;
use App\Services\JourneyService;
use App\Services\PaymentService;
use App\Services\QuoteService;
use App\Services\TaxHints;
use App\Support\NameSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        'customer' => "COALESCE(NULLIF(c.last_name, ''), NULLIF(c.company_name, ''), c.customer_number)",
        'date' => 'documents.document_date',
        'gross' => 'documents.gross_total',
        'status' => 'documents.status',
    ];

    public function __construct(
        private readonly DocumentService $documents,
        private readonly TaxHints $hints,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Angaben, die nicht in der Tabelle stehen, an die Antwort hängen: Hinweise zum Steuersatz (`warnings`, nur Warnungen,
     * siehe TaxHints) und den Zahlstand (`payment`, null bei Belegen ohne Zahlungen). Beides wird nie gespeichert.
     * JEDE Antwort mit einem einzelnen Beleg geht hier durch: Die Oberfläche übernimmt die Antwort als neuen Stand des Belegs
     * und blendet danach z. B. den Abschnitt „Zahlungen“ ein. Fehlte `payment` nach dem Festschreiben, erschiene er erst nach F5.
     */
    private function withHints(Document $document): Document
    {
        $document->loadMissing('lines');
        $document->setAttribute('payment', $this->payments->summary($document));
        $document->setAttribute('journey', app(JourneyService::class)->summary($document));

        return $document->setAttribute('warnings', $this->hints->forDocument($document));
    }

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
            ->with(['customer:id,customer_number,company_name,first_name,last_name,postal_code,city', 'source:id,type,number'])
            ->select('documents.*')
            ->leftJoin('customers as c', 'c.id', '=', 'documents.customer_id');

        foreach (['type', 'status', 'customer_id'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where('documents.'.$filter, $value);
            }
        }

        if ($q = trim((string) $request->query('q'))) {
            // Kundensuche am Wortanfang über den schon verbundenen Kunden `c` (kein Unterabfrage-Umweg), siehe NameSearch:
            // „Muster“, „Max Hamburg“, „Mustermann,Max“. Dazu je Wort: Belegnummer und Betreff. Ein Wort mit Ziffer trifft die
            // Nummer auch mittendrin („00010“), sonst nur am Anfang („RE“); der Betreff trifft am Wortanfang.
            // Belege des Kunden, der inzwischen archiviert ist, bleiben auffindbar.
            NameSearch::apply($query, $q, ['last_name', 'first_name', 'city', 'postal_code', 'customer_number', 'company_name', 'email'], 'c', function ($w, string $token) {
                $w->orWhere('documents.number', 'like', preg_match('/\d/', $token) ? NameSearch::anywhere($token) : NameSearch::start($token))
                    ->orWhere('documents.subject', 'like', NameSearch::start($token))
                    ->orWhere('documents.subject', 'like', '% '.NameSearch::start($token));
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

        $result = $query->paginate(20, ['*'], 'page', $page)->withQueryString();

        // Zahlstand je Zeile (Rechnungen und Anzahlungen): die Summen für die ganze Seite mit EINER Abfrage holen.
        $totals = $this->payments->paidTotals($result->getCollection()->pluck('id')->all());
        $result->getCollection()->each(fn (Document $d) => $d->setAttribute('payment', $this->payments->summary($d, $totals[$d->id] ?? 0)));

        return response()->json($result);
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
            ->orderByRaw("COALESCE(NULLIF(c.last_name, ''), NULLIF(c.company_name, ''), c.customer_number)")
            ->orderBy('c.id')
            ->orderBy('root.created_at')
            ->orderBy('root.id')
            ->orderBy('documents.created_at')
            ->orderBy('documents.id');
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json($this->withHints($document->load(['lines', 'customer', 'source:id,type,number', 'cancelledBy:id,number'])));
    }

    public function store(DocumentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $document = $this->documents->createDraft(DocumentType::from($data['type']), $data, $data['lines']);

        return response()->json($this->withHints($document), 201);
    }

    public function update(DocumentRequest $request, Document $document): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->withHints($this->documents->updateDraft($document, $data, $data['lines'])));
    }

    /**
     * Eine weitere Strecke der Reise in den Entwurf aufnehmen: Aus der gespeicherten Tour entstehen die Positionen je Fahrzeugtyp.
     * Der Beleg muss ein Entwurf mit Reise sein; die Positionen kommen mit den Preisen der Firma (Einstellungen › Preise).
     */
    public function addSection(Request $request, Document $document, QuoteService $quotes): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(SectionKind::class)],
            'trip_id' => ['required', 'uuid', 'exists:trips,id'],
            'start_on' => ['nullable', 'date_format:Y-m-d'],
            'surcharge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vehicles' => ['required', 'array', 'min:1', 'max:6'],
            'vehicles.*.type' => ['required', 'distinct', Rule::enum(VehicleType::class)],
            'vehicles.*.count' => ['required', 'integer', 'min:1', 'max:99'],
        ], [
            'vehicles.required' => 'Bitte mindestens einen Fahrzeugtyp wählen.',
            'vehicles.*.type.distinct' => 'Jeder Fahrzeugtyp darf nur einmal vorkommen. Erhöhen Sie stattdessen die Anzahl.',
        ]);

        $vehicles = array_map(fn (array $v) => ['type' => VehicleType::from($v['type']), 'count' => (int) $v['count']], $data['vehicles']);

        $updated = $quotes->addSection(
            $document, SectionKind::from($data['kind']), Trip::findOrFail($data['trip_id']), $vehicles,
            $data['start_on'] ?? null, (float) ($data['surcharge_percent'] ?? 0),
        );

        return response()->json($this->withHints($updated->load(['lines', 'customer', 'source:id,type,number'])), 201);
    }

    /** Eine Strecke samt ihren Positionen aus dem Entwurf nehmen. Die erste Strecke bleibt, dann gehört der Entwurf gelöscht. */
    public function removeSection(Document $document, JourneySection $section): JsonResponse
    {
        if ($document->journey_id === null || $section->journey_id !== $document->journey_id) {
            throw ValidationException::withMessages(['section' => ['Diese Strecke gehört nicht zur Reise dieses Belegs.']]);
        }
        if (JourneySection::where('journey_id', $document->journey_id)->count() <= 1) {
            throw ValidationException::withMessages(['section' => ['Die einzige Strecke der Reise kann nicht entfernt werden. Löschen Sie stattdessen den Entwurf.']]);
        }

        $updated = DB::transaction(function () use ($document, $section) {
            $updated = $this->documents->removeSectionLines($document, $section->id);
            // Der Abschnitt verschwindet nur, wenn kein anderer Beleg der Reise noch Positionen darauf hat.
            app(JourneyService::class)->removeSectionIfUnused($section);

            return $updated;
        });

        return response()->json($this->withHints($updated->load(['lines', 'customer', 'source:id,type,number'])));
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->documents->deleteDraft($document);

        return response()->json(['message' => 'Entwurf gelöscht.']);
    }

    /** Festschreiben: vergibt die Nummer und sperrt den Beleg. */
    public function finalize(Document $document): JsonResponse
    {
        return response()->json($this->withHints($this->documents->finalize($document)->load(['lines', 'customer'])));
    }

    /** Stornieren: erzeugt die Stornorechnung und gibt sie zurück. */
    public function cancel(Document $document): JsonResponse
    {
        return response()->json($this->withHints($this->documents->cancel($document)), 201);
    }

    /** Folgebeleg als Entwurf (Angebot → Auftragsbestätigung → Rechnung). */
    public function convert(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)],
            // Nur für die Anzahlungsrechnung: Anteil am Auftrag in Prozent (Vorbelegung 30).
            'percent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ]);

        return response()->json($this->withHints($this->documents->convert(
            $document,
            DocumentType::from($data['type']),
            isset($data['percent']) ? (float) $data['percent'] : null,
        )), 201);
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

    /**
     * E-Rechnung (XRechnung-XML) eines festgeschriebenen Belegs, als Download. Die Datei kommt aus dem Archiv (nach Prüfsumme),
     * nie neu erzeugt. Hat der Beleg noch keine (ältere Rechnung, Kunde erst später auf E-Rechnung umgestellt), entsteht sie beim
     * ersten Abruf einmal; fehlen dafür Angaben, nennt die Antwort sie (422). Der Abruf steht im Änderungsprotokoll.
     */
    public function einvoice(Document $document, EInvoiceService $einvoice): Response|JsonResponse
    {
        if ($document->isDraft() || ! $document->type->isInvoice()) {
            return response()->json(['message' => 'Eine E-Rechnung gibt es nur zu festgeschriebenen Rechnungen, Anzahlungsrechnungen und Stornorechnungen.'], 422);
        }

        try {
            $xml = $einvoice->read($document);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage().' Bitte die Verwaltung informieren, die Sicherung enthält die Datei.'], 500);
        }
        AuditLog::record('einvoice_downloaded', $document, null, ['number' => $document->number]);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$document->number.'.xml"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** ZUGFeRD-Fassung der E-Rechnung: archiviertes PDF mit eingebetteter XRechnung-XML, siehe `EInvoiceService::hybridPdf()`. */
    public function zugferd(Document $document, EInvoiceService $einvoice, DocumentArchive $archive): Response|JsonResponse
    {
        if ($document->isDraft() || ! $document->type->isInvoice()) {
            return response()->json(['message' => 'Eine E-Rechnung gibt es nur zu festgeschriebenen Rechnungen, Anzahlungsrechnungen und Stornorechnungen.'], 422);
        }

        try {
            $pdf = $einvoice->hybridPdf($document, $archive);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage().' Bitte die Verwaltung informieren, die Sicherung enthält die Datei.'], 500);
        }
        AuditLog::record('zugferd_downloaded', $document, null, ['number' => $document->number]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->number.'-zugferd.pdf"',
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
