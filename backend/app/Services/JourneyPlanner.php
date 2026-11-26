<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Journey;
use App\Models\JourneySection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Die Reise Schritt für Schritt planen und daraus das Angebot ableiten.
 *
 * Der Nutzer legt Strecken (Hinfahrt, Fahrt vor Ort, Rückfahrt) auf der Reise an; „Angebot erstellen“ macht daraus einen Entwurf mit
 * allen Strecken. Ändert oder ergänzt er später eine Strecke, gleicht „Angebot aktualisieren“ die Positionen DIESER Strecke im Entwurf
 * ab. Positionen unveränderter Strecken bleiben mit allen Handänderungen unberührt. Nach dem Festschreiben ist Schluss.
 *
 * Zustand einer Strecke gegenüber dem Angebot, also dem Entwurf, sonst dem festgeschriebenen (Signatur: QuoteService::signature):
 *  - neu       keine Positionen im Angebot (oder es gibt noch kein Angebot)
 *  - geaendert Positionen vorhanden, aber die Eingaben haben sich seit der Berechnung geändert
 *  - aktuell   sonst (auch bei unbekannter Signatur, damit Bestandsdaten nie ungefragt überschrieben werden)
 */
class JourneyPlanner
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly DocumentService $documents,
        private readonly JourneyService $journeys,
    ) {}

    /** Der Angebot-Entwurf der Reise (der neueste), sonst null. */
    public function draft(Journey $journey): ?Document
    {
        return Document::where('journey_id', $journey->id)
            ->where('type', DocumentType::Angebot->value)->where('status', DocumentStatus::Entwurf->value)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    /** Das neueste nicht stornierte Angebot, das schon festgeschrieben ist, sonst null. */
    private function issuedOffer(Journey $journey): ?Document
    {
        return Document::where('journey_id', $journey->id)
            ->where('type', DocumentType::Angebot->value)->where('status', '!=', DocumentStatus::Entwurf->value)
            ->whereNull('cancelled_by_id')->orderByDesc('created_at')->first();
    }

    /**
     * Stand von Reise und Angebot für die Oberfläche.
     *
     * @return array{draft_id: ?string, issued_id: ?string, sections: list<array{id: string, state: string}>, can_create: bool, can_sync: bool, pending: int}
     */
    public function status(Journey $journey): array
    {
        $draft = $this->draft($journey);
        $issued = $draft ? null : $this->issuedOffer($journey);
        // Ohne Entwurf zählt das festgeschriebene Angebot: Seine Strecken sind nicht „neu“.
        $sections = $this->states($journey, $draft ?? $issued);
        $pending = count(array_filter($sections, fn (array $s) => $s['state'] !== 'aktuell'));

        return [
            'draft_id' => $draft?->id,
            'issued_id' => $issued?->id,
            'sections' => $sections,
            'can_create' => ! $journey->isCancelled() && $draft === null && $issued === null && count($sections) > 0,
            'can_sync' => ! $journey->isCancelled() && $draft !== null && $pending > 0,
            'pending' => $pending,
        ];
    }

    /** @return list<array{id: string, state: string}> */
    private function states(Journey $journey, ?Document $offer): array
    {
        $withLines = $offer
            ? DocumentLine::where('document_id', $offer->id)->whereNotNull('journey_section_id')->pluck('journey_section_id')->unique()->flip()
            : collect();

        return JourneySection::where('journey_id', $journey->id)->with('vehicles')->orderBy('position')->get()
            ->map(function (JourneySection $s) use ($withLines) {
                $state = match (true) {
                    ! $withLines->has($s->id) => 'neu',
                    $s->priced_signature !== null && $s->priced_signature !== $this->quotes->signature($s) => 'geaendert',
                    default => 'aktuell',
                };

                return ['id' => $s->id, 'state' => $state];
            })->all();
    }

    /**
     * „Angebot erstellen“ oder „Angebot aktualisieren“, je nachdem, ob es schon einen Entwurf gibt.
     *
     * @return array{id: string, created: bool, updated: list<string>}  `updated`: Titel der neu berechneten Strecken
     */
    public function quote(Journey $journey): array
    {
        return DB::transaction(function () use ($journey) {
            $this->journeys->assertActive($journey->id);
            $draft = $this->draft($journey);

            if ($draft === null) {
                if ($this->issuedOffer($journey) !== null) {
                    $this->fail('Das Angebot dieser Reise ist schon festgeschrieben. Änderungen an den Strecken wirken nicht mehr auf dieses Angebot.');
                }

                return $this->create($journey);
            }

            return $this->sync($journey, $draft);
        });
    }

    /** @return array{id: string, created: bool, updated: list<string>} */
    private function create(Journey $journey): array
    {
        $sections = JourneySection::where('journey_id', $journey->id)->with('vehicles')->orderBy('position')->get();
        if ($sections->isEmpty()) {
            $this->fail('Die Reise hat noch keine Strecke. Bitte zuerst eine Strecke hinzufügen.');
        }

        // Erst alles berechnen: Fehlen Preise für eine Strecke, entsteht kein halbes Angebot.
        $lines = [];
        foreach ($sections as $s) {
            array_push($lines, ...$this->quotes->linesForSection($s));
        }

        $document = $this->documents->createDraft(DocumentType::Angebot, [
            'customer_id' => $journey->customer_id,
            'subject' => mb_substr($journey->title, 0, 255),
            'service_date_from' => $journey->date_from?->toDateString(),
            'service_date_to' => $journey->date_to?->toDateString(),
        ], $lines, $journey->id);

        $this->stamp($sections);

        return ['id' => $document->id, 'created' => true, 'updated' => $sections->pluck('title')->all()];
    }

    /** @return array{id: string, created: bool, updated: list<string>} */
    private function sync(Journey $journey, Document $draft): array
    {
        $states = collect($this->states($journey, $draft))->keyBy('id');
        $todo = JourneySection::where('journey_id', $journey->id)->with('vehicles')->orderBy('position')->get()
            ->filter(fn (JourneySection $s) => $states[$s->id]['state'] !== 'aktuell')->values();

        // Erst alles berechnen, dann ändern: scheitert die Preisfindung einer Strecke, bleibt der Entwurf, wie er war.
        $new = [];
        foreach ($todo as $s) {
            $new[$s->id] = $this->quotes->linesForSection($s);
        }
        foreach ($todo as $s) {
            $this->documents->removeSectionLines($draft, $s->id);
            $this->documents->appendLines($draft, $new[$s->id]);
        }
        $this->stamp($todo);

        return ['id' => $draft->id, 'created' => false, 'updated' => $todo->pluck('title')->all()];
    }

    /** Merkt für jede Strecke, mit welchen Eingaben ihre Positionen berechnet wurden. */
    private function stamp(iterable $sections): void
    {
        foreach ($sections as $s) {
            $s->forceFill(['priced_signature' => $this->quotes->signature($s->fresh('vehicles'))])->save();
        }
    }

    /**
     * Strecke entfernen. Zeigt ein festgeschriebener Beleg auf sie, geht es nicht (er behält seinen Stand). Positionen im Entwurf
     * werden mit entfernt.
     */
    public function removeSection(JourneySection $section): void
    {
        DB::transaction(function () use ($section) {
            $this->journeys->assertActive($section->journey_id);
            $issued = DocumentLine::where('journey_section_id', $section->id)
                ->whereHas('document', fn ($q) => $q->where('status', '!=', DocumentStatus::Entwurf->value))->exists();
            if ($issued) {
                $this->fail('Diese Strecke steht in einem festgeschriebenen Beleg und lässt sich nicht mehr entfernen.');
            }

            $drafts = Document::where('journey_id', $section->journey_id)->where('status', DocumentStatus::Entwurf->value)
                ->whereHas('lines', fn ($q) => $q->where('journey_section_id', $section->id))->get();
            foreach ($drafts as $draft) {
                $this->documents->removeSectionLines($draft, $section->id);
            }

            $journey = Journey::findOrFail($section->journey_id);
            $section->delete();
            $this->journeys->refreshDates($journey);
        });
    }

    /** @throws ValidationException */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['journey' => [$message]]);
    }
}
