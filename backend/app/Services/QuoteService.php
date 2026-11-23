<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\GermanState;
use App\Enums\SectionKind;
use App\Enums\TripKind;
use App\Enums\VehicleType;
use App\Models\CompanySetting;
use App\Models\Document;
use App\Models\JourneySection;
use App\Models\PricingSetting;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Pricing\GermanHolidays;
use App\Pricing\PriceCalculator;
use App\Pricing\TourDay;
use App\Pricing\TourFigures;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verbindet Streckenplanung, Preisfindung und Belege: aus den Kennzahlen einer geplanten Tour entsteht ein Angebot als Entwurf.
 * Die Module kennen sich nicht gegenseitig: Dieser Dienst reicht nur Zahlen und Positionen weiter.
 */
class QuoteService
{
    public function __construct(
        private readonly PriceCalculator $prices,
        private readonly DocumentService $documents,
        private readonly JourneyService $journeys,
    ) {}

    /**
     * @param  array{loaded_km: float, empty_km: float, drive_s: int, days: int, surcharge_percent?: float, label?: ?string, start_at?: ?string, end_at?: ?string, state?: ?string, vehicle_id?: ?string, trip_kind?: ?string}  $tour
     */
    public function draftFromTour(array $tour, ?string $customerId = null): Document
    {
        // Der Typ bestimmt die Preise: der gewählte Fahrzeugtyp, sonst der Typ des Fahrzeugs; nur der Typ geht weiter, nicht das Fahrzeug selbst.
        $vehicleType = VehicleType::tryFrom((string) ($tour['vehicle_type'] ?? ''))
            ?? (! empty($tour['vehicle_id']) ? Vehicle::withTrashed()->find($tour['vehicle_id'])?->type : null);
        $count = max(1, (int) ($tour['vehicle_count'] ?? 1));

        $lines = $this->priceLines($tour, $vehicleType);

        $label = trim((string) ($tour['label'] ?? ''));
        $from = ! empty($tour['start_at']) ? $this->localDay($tour['start_at'])->toDateString() : null;
        $to = ! empty($tour['end_at']) ? $this->localDay($tour['end_at'])->toDateString() : null;

        // Mit dem ersten Angebot entsteht das Reisevorhaben von selbst (Hinfahrt mit der gespeicherten Tour, falls es eine gibt).
        return DB::transaction(function () use ($customerId, $label, $from, $to, $lines, $tour, $vehicleType, $count) {
            $journey = $this->journeys->start($label, $customerId, $from, $to, $tour['trip_id'] ?? null, [['type' => $vehicleType, 'count' => $count]]);
            $lines = $this->tagLines($lines, $journey->sections()->firstOrFail(), $vehicleType, $count);

            $document = $this->documents->createDraft(DocumentType::Angebot, [
                'customer_id' => $customerId,
                'subject' => $label !== '' ? mb_substr('Busfahrt '.$label, 0, 255) : 'Busfahrt',
                'service_date_from' => $from,
            ], $lines, $journey->id);

            return $document;
        });
    }

    /**
     * Fügt einem Entwurf eine weitere Strecke der Reise hinzu: Aus der gespeicherten Tour entstehen je Fahrzeugtyp Positionen mit den
     * Preisen dieses Typs, jede Position mal Anzahl der Fahrzeuge. Der Abschnitt und sein Fahrzeugbedarf werden mit angelegt.
     *
     * @param  list<array{type: VehicleType, count: int}>  $vehicles
     */
    public function addSection(Document $draft, SectionKind $kind, Trip $trip, array $vehicles, ?string $startOn, float $surchargePercent = 0, ?string $state = null): Document
    {
        if (! $draft->isDraft()) {
            throw ValidationException::withMessages(['section' => ['Dieser Beleg ist festgeschrieben. Strecken lassen sich nur im Entwurf hinzufügen.']]);
        }
        if ($draft->journey_id === null) {
            throw ValidationException::withMessages(['section' => ['Dieser Beleg gehört zu keiner Reise. Weitere Strecken gibt es nur bei Angeboten aus der Streckenplanung.']]);
        }

        $tour = [
            'loaded_km' => $trip->loaded_km, 'empty_km' => $trip->empty_km, 'drive_s' => $trip->drive_s, 'days' => $trip->days,
            'trip_kind' => $trip->trip_kind->value, 'surcharge_percent' => $surchargePercent, 'state' => $state,
            // Mittags gewählt, damit der Kalendertag in deutscher Zeit derselbe bleibt.
            'start_at' => $startOn ? $startOn.'T12:00:00Z' : null,
        ];

        return DB::transaction(function () use ($draft, $kind, $trip, $vehicles, $tour) {
            // Erst alles berechnen: Fehlen Preise, entsteht kein halber Abschnitt.
            $priced = array_map(fn (array $v) => $this->priceLines($tour, $v['type']), $vehicles);

            $section = $this->journeys->addSection($draft->journey_id, $kind, $trip, $vehicles);
            $new = [];
            foreach ($vehicles as $i => $v) {
                array_push($new, ...$this->tagLines($priced[$i], $section, $v['type'], $v['count']));
            }

            return $this->documents->appendLines($draft, $new);
        });
    }

    /**
     * Die Positionen für eine Tour und einen Fahrzeugtyp aus den Preisen der Firma.
     *
     * @return list<array<string, mixed>>
     */
    private function priceLines(array $tour, ?VehicleType $vehicleType): array
    {
        if (! PricingSetting::current()->hasAnyPrice($vehicleType)) {
            throw ValidationException::withMessages(['pricing' => ['In den Einstellungen sind noch keine Preise hinterlegt. Bitte zuerst unter „Preise“ eintragen.']]);
        }

        $lines = $this->prices->quote(new TourFigures(
            loadedKm: (float) $tour['loaded_km'],
            emptyKm: (float) $tour['empty_km'],
            driveSeconds: (int) $tour['drive_s'],
            days: max(1, (int) $tour['days']),
            surchargePercent: (float) ($tour['surcharge_percent'] ?? 0),
            vehicleType: $vehicleType,
            calendarDays: $this->calendarDays($tour),
            // Ohne Angabe: Gelegenheitsverkehr (Regelsatz). Linienverkehr und Schülerfahrt müssen gewählt werden.
            tripKind: TripKind::tryFrom((string) ($tour['trip_kind'] ?? '')) ?? TripKind::Gelegenheit,
        ));

        if ($lines === []) {
            throw ValidationException::withMessages(['pricing' => ['Mit den hinterlegten Preisen ergibt diese Tour keine Position.']]);
        }

        return $lines;
    }

    /**
     * Hängt die Positionen an ihren Abschnitt und Fahrzeugtyp. Mehrere gleiche Fahrzeuge: jede Position mal Anzahl. Die Beschreibung
     * nennt Abschnitt, Strecke und Fahrzeuge, damit jede Position auch im PDF und in der E-Rechnung für sich verständlich ist.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function tagLines(array $lines, JourneySection $section, ?VehicleType $type, int $count): array
    {
        $note = trim($section->kind->label().': '.$section->title);
        if ($type !== null) {
            $note .= ' · '.($count > 1 ? "$count × " : '').$type->label();
        }

        return array_map(fn (array $l) => [
            'quantity' => $count > 1 ? rtrim(rtrim(number_format((float) $l['quantity'] * $count, 3, '.', ''), '0'), '.') : $l['quantity'],
            'description' => $note.($l['description'] ? ' · '.$l['description'] : ''),
            'journey_section_id' => $section->id,
            'vehicle_type' => $type?->value,
        ] + $l, $lines);
    }

    /** Der Kalendertag eines Zeitpunkts in deutscher Zeit (der Browser sendet UTC: 00:30 Uhr hier ist dort noch der Vortag). */
    private function localDay(string $at): Carbon
    {
        return Carbon::parse($at)->setTimezone('Europe/Berlin')->startOfDay();
    }

    /**
     * Die Kalendertage der Tour vom Start- bis zum Endtag mit Samstag, Sonntag und Feiertag. Ohne Starttermin gibt es keine Tage.
     * Das Bundesland der Feiertage kommt aus der Tour, sonst vom Betriebssitz; ohne beides zählen nur Samstag und Sonntag.
     *
     * @return list<TourDay>
     */
    private function calendarDays(array $tour): array
    {
        if (empty($tour['start_at'])) {
            return [];
        }

        $first = $this->localDay($tour['start_at']);
        // Ohne Endzeit rechnen wir mit den Einsatztagen; ein Ende vor dem Start zählt nicht.
        $last = ! empty($tour['end_at']) ? $this->localDay($tour['end_at']) : $first->copy()->addDays(max(1, (int) $tour['days']) - 1);
        if ($last->lt($first)) {
            $last = $first->copy();
        }

        $state = GermanState::tryFrom((string) ($tour['state'] ?? '')) ?? GermanState::tryFrom((string) CompanySetting::current()->state);

        $days = [];
        for ($d = $first->copy(); $d->lte($last) && count($days) < 62; $d->addDay()) {
            $days[] = new TourDay($d->toDateString(), $d->isSaturday(), $d->isSunday(), $state ? GermanHolidays::name($d, $state) : null);
        }

        return $days;
    }
}
