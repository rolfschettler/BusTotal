<?php

namespace App\Http\Controllers\Api;

use App\Enums\SectionKind;
use App\Enums\TripKind;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\TourPayload;
use App\Models\Document;
use App\Models\Journey;
use App\Models\JourneySection;
use App\Models\Trip;
use App\Services\DocumentService;
use App\Services\JourneyCancellationService;
use App\Services\JourneyPlanner;
use App\Services\JourneyService;
use App\Support\NameSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reisen: Hier plant man eine Reise Schritt für Schritt. Man legt sie an (Titel, Kunde), fügt Strecken hinzu (Hinfahrt, Fahrt vor Ort,
 * Rückfahrt) und erzeugt daraus das Angebot, das sich bei Änderungen abgleichen lässt (JourneyPlanner). Die Reise entsteht auch mit
 * dem Kurzweg „Angebot erstellen“ aus der Streckenplanung. Summen und Stand werden aus den Belegen abgeleitet (JourneyService).
 */
class JourneyController extends Controller
{
    public function __construct(
        private readonly JourneyService $journeys,
        private readonly JourneyPlanner $planner,
        private readonly DocumentService $documents,
        private readonly JourneyCancellationService $cancellations,
    ) {}

    private const VIEWS = ['laufende', 'abgeschlossen', 'abgesagt', 'alle'];

    /**
     * Neueste Reisen zuerst, nach Ansicht (Vorgabe „laufende“). Die Suche trifft Titel und Kunde am Wortanfang (siehe NameSearch);
     * `other_matches` zählt dann die Treffer, die in der gewählten Ansicht nicht zu sehen sind.
     */
    public function index(Request $request): JsonResponse
    {
        $view = $request->validate(['view' => ['nullable', Rule::in(self::VIEWS)]], ['view.in' => 'Diese Ansicht gibt es nicht.'])['view'] ?? 'laufende';

        $query = Journey::query()
            ->select('journeys.*')
            ->with('customer:id,customer_number,company_name,first_name,last_name,postal_code,city')
            ->withCount('sections')
            ->leftJoin('customers as c', 'c.id', '=', 'journeys.customer_id');

        $q = trim((string) $request->query('q'));
        if ($q !== '') {
            NameSearch::apply($query, $q, ['last_name', 'first_name', 'city', 'postal_code', 'customer_number', 'company_name', 'email'], 'c', function ($w, string $token) {
                $w->orWhere('journeys.title', 'like', NameSearch::start($token))
                    ->orWhere('journeys.title', 'like', '% '.NameSearch::start($token));
            });
        }

        // Vor dem Filter der Ansicht zählen: alle Treffer der Suche
        $matches = $q !== '' && $view !== 'alle' ? (clone $query)->count('journeys.id') : null;
        match ($view) {
            'laufende' => $query->running(),
            'abgeschlossen' => $query->completed(),
            'abgesagt' => $query->cancelled(),
            default => null,
        };

        $result = $query->orderByDesc('journeys.created_at')->orderByDesc('journeys.id')->paginate(20)->withQueryString();

        // Stand und Betrag je Reise: die Belege der ganzen Seite mit EINER Abfrage holen.
        $ids = $result->getCollection()->pluck('id');
        $docs = Document::whereIn('journey_id', $ids)->get()->groupBy('journey_id');
        $completed = Journey::query()->completed()->whereIn('journeys.id', $ids)->pluck('journeys.id')->flip();
        $result->getCollection()->each(function (Journey $j) use ($docs, $completed) {
            $j->setAttribute('state', $j->isCancelled() ? 'abgesagt' : ($completed->has($j->id) ? 'abgeschlossen' : 'laufend'));
            foreach ($this->journeys->overview($docs->get($j->id, [])) as $key => $value) {
                $j->setAttribute($key, $value);
            }
        });

        return response()->json($result->toArray() + ['other_matches' => $matches === null ? 0 : max(0, $matches - $result->total())]);
    }

    /** Neue Reise von Hand planen: Titel und (freiwillig) Kunde. Strecken kommen danach auf der Reise hinzu. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
        ], $this->titleMessages());

        $journey = Journey::create(['title' => trim($data['title']), 'customer_id' => $data['customer_id'] ?? null, 'manual' => true]);

        return response()->json($this->payload($journey), 201);
    }

    /** Titel und Kunde ändern. Beide stehen auf keinem festgeschriebenen Beleg (der hat seine Kopie). */
    public function update(Request $request, Journey $journey): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
        ], $this->titleMessages());

        $journey->update(['title' => trim($data['title'])] + (array_key_exists('customer_id', $data) ? ['customer_id' => $data['customer_id']] : []));

        return response()->json($this->payload($journey->fresh()));
    }

    /** Eine Reise ohne Belege löschen (zum Beispiel eine angefangene Planung). */
    public function destroy(Journey $journey): JsonResponse
    {
        $docs = Document::where('journey_id', $journey->id)->get();
        if ($docs->contains(fn (Document $d) => ! $d->isDraft())) {
            throw ValidationException::withMessages(['journey' => [
                'Zu dieser Reise gibt es festgeschriebene Belege. Die lassen sich nicht löschen, damit auch die Reise nicht. Findet die Reise nicht statt, nehmen Sie „Reise absagen“.',
            ]]);
        }

        // Nur Entwürfe: Sie gehören zur Planung und verschwinden mit der Reise (neueste zuerst, Folgebelege vor ihrer Quelle).
        DB::transaction(function () use ($docs, $journey) {
            foreach ($docs->sortByDesc('created_at') as $draft) {
                $this->documents->deleteDraft($draft);
            }
            Journey::whereKey($journey->id)->first()?->delete();
        });

        return response()->json(['message' => 'Reise gelöscht.']);
    }

    public function show(Journey $journey): JsonResponse
    {
        return response()->json($this->payload($journey));
    }

    // ---------- Absage ----------

    /** Was die Absage bewirkt: Fahrtaufträge und schon eingegangene Beträge. */
    public function cancellation(Journey $journey): JsonResponse
    {
        return response()->json($this->cancellations->preview($journey));
    }

    public function cancel(Request $request, Journey $journey): JsonResponse
    {
        // „Heute“ ist der Tag in Deutschland, der Server rechnet in UTC.
        $today = Carbon::today('Europe/Berlin')->toDateString();
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'date.required' => 'Bitte angeben, wann die Reise abgesagt wurde.',
            'date.date_format' => 'Bitte ein gültiges Datum angeben.',
            'date.before_or_equal' => 'Das Datum der Absage darf nicht in der Zukunft liegen.',
            'reason.required' => 'Bitte einen Grund für die Absage angeben.',
            'reason.max' => 'Der Grund darf höchstens 255 Zeichen lang sein.',
        ]);

        return response()->json($this->payload($this->cancellations->cancel($journey, $data['date'], $data['reason'])));
    }

    public function reactivate(Journey $journey): JsonResponse
    {
        return response()->json($this->payload($this->cancellations->reactivate($journey)));
    }

    // ---------- Strecken ----------

    /**
     * Strecke hinzufügen: aus einer Tour des Archivs (`trip_id`), aus einer in der Streckenplanung berechneten Tour (`tour`, ohne
     * Archiv-Eintrag) oder aus eigenen Angaben (`own`, etwa für eine Fahrt vor Ort). Die Strecke trägt ihre eigene Kopie der Tour.
     */
    public function storeSection(Request $request, Journey $journey): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(SectionKind::class)],
            'trip_id' => ['required_without_all:own,tour', 'prohibits:own,tour', 'nullable', 'uuid', 'exists:trips,id'],
            'own' => ['required_without_all:trip_id,tour', 'prohibits:tour', 'array'],
            'own.title' => ['required_with:own', 'string', 'max:150'],
            'own.loaded_km' => ['required_with:own', 'numeric', 'min:0.1', 'max:5000'],
            'own.drive_minutes' => ['required_with:own', 'integer', 'min:1', 'max:6000'],
            'own.days' => ['required_with:own', 'integer', 'min:1', 'max:60'],
            ...TourPayload::rules('tour'),
            // Von Hand geänderte Werte einer berechneten Route
            ...$this->figureRules('min:0'),
            ...$this->sectionRules(),
        ], $this->sectionMessages());

        $trip = ! empty($data['trip_id']) ? Trip::findOrFail($data['trip_id']) : null;
        $tour = $data['tour'] ?? null;
        $own = $trip || $tour ? null : [
            'title' => $data['own']['title'], 'loaded_km' => round((float) $data['own']['loaded_km'], 1),
            'drive_s' => (int) $data['own']['drive_minutes'] * 60, 'days' => (int) $data['own']['days'],
        ];

        DB::transaction(function () use ($journey, $data, $trip, $own, $tour) {
            $section = $this->journeys->addSection(
                $journey->id, SectionKind::from($data['kind']), $trip, $this->vehicles($data['vehicles']), $own, $trip ? null : $tour,
                $data['start_on'] ?? null, (float) ($data['surcharge_percent'] ?? 0),
            );
            if ($own === null && ($figures = $this->figures($data))) {
                $this->journeys->updateSection($section, [], null, null, $figures);
            }
        });

        return response()->json($this->payload($journey->fresh()), 201);
    }

    /** Eine Strecke mit ihrem Plan, zum Bearbeiten in der Streckenplanung. */
    public function showSection(Journey $journey, JourneySection $section): JsonResponse
    {
        $this->assertBelongs($journey, $section);

        return response()->json($this->journeys->sectionForEdit($journey, $section));
    }

    /** Titel, Datum, Zuschlag, Fahrzeuge, Route und Kennzahlen einer Strecke ändern. Das Angebot zieht mit „Angebot aktualisieren“ nach. */
    public function updateSection(Request $request, Journey $journey, JourneySection $section): JsonResponse
    {
        $this->assertBelongs($journey, $section);
        // Ohne Route (eigene Angaben) braucht die Strecke Kilometer, sonst gäbe es keinen Preis.
        $minKm = $section->tour_data === null && ! $request->filled('tour') ? 'min:0.1' : 'min:0';
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            ...TourPayload::rules('tour'),
            'trip_kind' => ['nullable', Rule::enum(TripKind::class)],
            ...$this->figureRules($minKm),
        ] + $this->sectionRules(vehiclesRequired: false), $this->sectionMessages());

        $changes = [];
        if (! empty($data['title'])) {
            $changes['title'] = trim($data['title']);
        }
        if (array_key_exists('start_on', $data)) {
            $changes['start_on'] = $data['start_on'];
        }
        if (array_key_exists('surcharge_percent', $data)) {
            $changes['surcharge_percent'] = (float) ($data['surcharge_percent'] ?? 0);
        }

        if (! empty($data['trip_kind'])) {
            $changes['trip_kind'] = TripKind::from($data['trip_kind']);
        }

        $this->journeys->updateSection($section, $changes, isset($data['vehicles']) ? $this->vehicles($data['vehicles']) : null, $data['tour'] ?? null, $this->figures($data));

        return response()->json($this->payload($journey->fresh()));
    }

    public function destroySection(Journey $journey, JourneySection $section): JsonResponse
    {
        $this->assertBelongs($journey, $section);
        $this->planner->removeSection($section);

        return response()->json($this->payload($journey->fresh()));
    }

    // ---------- Angebot ----------

    public function quoteStatus(Journey $journey): JsonResponse
    {
        return response()->json($this->planner->status($journey));
    }

    /** „Angebot erstellen“ (noch kein Entwurf) oder „Angebot aktualisieren“ (Entwurf: nur neue und geänderte Strecken neu berechnen). */
    public function quote(Journey $journey): JsonResponse
    {
        $result = $this->planner->quote($journey);

        return response()->json($result, $result['created'] ? 201 : 200);
    }

    // ---------- Helfer ----------

    /** Die Reise für die Oberfläche: Detail mit Strecken, Belegen und dem Stand des Angebots. */
    private function payload(Journey $journey): array
    {
        return $this->journeys->detail($journey) + ['quote' => $this->planner->status($journey)];
    }

    private function assertBelongs(Journey $journey, JourneySection $section): void
    {
        abort_unless($section->journey_id === $journey->id, 404);
    }

    /** @return array<string, list<mixed>> die Werte, aus denen der Preis entsteht */
    private function figureRules(string $minKm): array
    {
        return [
            'figures' => ['nullable', 'array'],
            'figures.loaded_km' => ['required_with:figures', 'numeric', $minKm, 'max:50000'],
            'figures.empty_km' => ['required_with:figures', 'numeric', 'min:0', 'max:50000'],
            'figures.drive_s' => ['required_with:figures', 'integer', 'min:60', 'max:3000000'],
            'figures.days' => ['required_with:figures', 'integer', 'min:1', 'max:60'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  geprüfte Eingabe
     * @return array{loaded_km: float, empty_km: float, drive_s: int, days: int}|null
     */
    private function figures(array $data): ?array
    {
        return isset($data['figures']) ? [
            'loaded_km' => round((float) $data['figures']['loaded_km'], 1), 'empty_km' => round((float) $data['figures']['empty_km'], 1),
            'drive_s' => (int) $data['figures']['drive_s'], 'days' => (int) $data['figures']['days'],
        ] : null;
    }

    /** @return array<string, list<mixed>> */
    private function sectionRules(bool $vehiclesRequired = true): array
    {
        return [
            'start_on' => ['nullable', 'date_format:Y-m-d'],
            'surcharge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vehicles' => [$vehiclesRequired ? 'required' : 'sometimes', 'array', 'min:1', 'max:6'],
            'vehicles.*.type' => ['required', 'distinct', Rule::enum(VehicleType::class)],
            'vehicles.*.count' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /** @return array<string, string> */
    private function sectionMessages(): array
    {
        return [
            'vehicles.required' => 'Bitte mindestens einen Fahrzeugtyp wählen.',
            'vehicles.*.type.distinct' => 'Jeder Fahrzeugtyp darf nur einmal vorkommen. Erhöhen Sie stattdessen die Anzahl.',
            'trip_id.required_without_all' => 'Bitte eine Tour aus dem Archiv wählen oder eigene Angaben machen.',
            'own.title.required_with' => 'Bitte eine Bezeichnung angeben, zum Beispiel „Ausflug zum Ossiacher See“.',
            'own.loaded_km.required_with' => 'Bitte die Kilometer angeben.',
            'own.loaded_km.min' => 'Bitte die Kilometer angeben.',
            'own.drive_minutes.required_with' => 'Bitte die Fahrzeit angeben.',
            'own.drive_minutes.min' => 'Bitte die Fahrzeit angeben.',
            'figures.loaded_km.min' => 'Bitte die Kilometer angeben.',
            'figures.drive_s.min' => 'Bitte die Fahrzeit angeben.',
            'figures.days.min' => 'Bitte die Anzahl der Tage angeben (mindestens 1).',
        ];
    }

    /** @return array<string, string> */
    private function titleMessages(): array
    {
        return [
            'title.required' => 'Bitte einen Namen für die Reise eingeben.',
            'title.max' => 'Der Name darf höchstens 150 Zeichen lang sein.',
        ];
    }

    /**
     * @param  list<array{type: string, count: int|string}>  $rows
     * @return list<array{type: VehicleType, count: int}>
     */
    private function vehicles(array $rows): array
    {
        return array_map(fn (array $v) => ['type' => VehicleType::from($v['type']), 'count' => (int) $v['count']], $rows);
    }
}
