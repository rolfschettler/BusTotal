<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\SectionKind;
use App\Enums\VehicleType;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Journey;
use App\Models\JourneySection;
use App\Models\Trip;
use App\Models\TripOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reisevorhaben: entstehen automatisch mit dem ersten Angebot aus der Streckenplanung, niemand legt sie von Hand an.
 * Der Dienst hält nur die Klammer (Kunde, Titel, Zeitraum, Abschnitte); Summen und Stand werden aus den Belegen abgeleitet.
 */
class JourneyService
{
    public function __construct(private readonly TourSnapshotService $snapshots, private readonly SectionPlanService $plans) {}

    /**
     * Eine abgesagte Reise wird nicht weiter geplant. Innerhalb einer Transaktion aufrufen: Die Reise wird gesperrt, damit eine
     * gleichzeitige Absage nicht dazwischenkommt.
     */
    public function assertActive(?string $journeyId, string $field = 'journey'): void
    {
        if ($journeyId !== null && Journey::whereKey($journeyId)->lockForUpdate()->first()?->isCancelled()) {
            throw ValidationException::withMessages([$field => ['Diese Reise ist abgesagt. Nehmen Sie zuerst auf der Reise die Absage zurück.']]);
        }
    }

    /** Stand der Reise: „abgesagt“, „abgeschlossen“ oder „laufend“. */
    public function state(Journey $journey): string
    {
        return match (true) {
            $journey->isCancelled() => 'abgesagt',
            Journey::query()->completed()->whereKey($journey->id)->exists() => 'abgeschlossen',
            default => 'laufend',
        };
    }

    /** @return array{state: string, cancelled_on: ?string, cancel_reason: ?string} */
    private function stateFields(Journey $journey): array
    {
        return ['state' => $this->state($journey), 'cancelled_on' => $journey->cancelled_on?->toDateString(), 'cancel_reason' => $journey->cancel_reason];
    }

    /**
     * Legt ein Reisevorhaben mit seinem ersten Abschnitt (Hinfahrt) an.
     *
     * @param  list<array{type: ?VehicleType, count: int}>  $vehicles  Fahrzeugbedarf der Hinfahrt
     * @param  array<string, mixed>|null  $tour  berechnete Tour ohne Archiv-Eintrag (nur wenn $tripId fehlt), siehe TourSnapshotService::fromRequest
     */
    public function start(string $title, ?string $customerId, ?string $dateFrom, ?string $dateTo, ?string $tripId, array $vehicles = [], ?array $tour = null): Journey
    {
        return DB::transaction(function () use ($title, $customerId, $dateFrom, $dateTo, $tripId, $vehicles, $tour) {
            $title = mb_substr(trim($title) !== '' ? trim($title) : 'Reise', 0, 150);
            $journey = Journey::create(['title' => $title, 'customer_id' => $customerId, 'date_from' => $dateFrom, 'date_to' => $dateTo]);
            // Die Strecke heißt wie ihre Tour (wie jede weitere Strecke); ohne gespeicherte Tour wie die Reise.
            // Mit der Tour entsteht auch die eigene Kopie der Strecke: Das Archiv ist danach nur noch Vorlage (siehe tour_data).
            $trip = $tripId ? Trip::with('stops')->find($tripId) : null;
            $section = $journey->sections()->create([
                'position' => 1, 'kind' => SectionKind::Hinfahrt, 'title' => mb_substr($trip?->title ?? $title, 0, 150),
                'trip_id' => $trip?->id,
                // Aus dem Archiv, sonst aus den Angaben der Berechnung (das Angebot legt nichts im Tour-Archiv an)
                'tour_data' => $trip ? $this->snapshots->tripData($trip) : ($tour ? $this->snapshots->fromRequest($tour, $title) : null),
                'plan' => $trip ? $this->plans->fromTrip($trip) : $this->plans->fromRequest($tour),
            ]);
            $this->saveVehicles($section, $vehicles);

            return $journey;
        });
    }

    /**
     * Ein weiterer Abschnitt der Reise mit seinem Fahrzeugbedarf: mit der Strecke einer gespeicherten Tour, oder ohne Tour mit
     * eigenen Angaben (Bezeichnung, Kilometer, Fahrzeit, Tage), zum Beispiel für eine Fahrt vor Ort.
     *
     * @param  list<array{type: ?VehicleType, count: int}>  $vehicles
     * @param  array{title: string, loaded_km: float, drive_s: int, days: int}|null  $own  nur ohne Tour
     * @param  array<string, mixed>|null  $tour  berechnete Tour ohne Archiv-Eintrag (nur ohne $trip), siehe TourSnapshotService::fromRequest
     */
    public function addSection(string $journeyId, SectionKind $kind, ?Trip $trip, array $vehicles, ?array $own = null, ?array $tour = null, ?string $startOn = null, float $surchargePercent = 0): JourneySection
    {
        return DB::transaction(function () use ($journeyId, $kind, $trip, $vehicles, $own, $tour, $startOn, $surchargePercent) {
            $this->assertActive($journeyId);
            // Die Reise sperren: Zwei gleichzeitige Aufrufe bekämen sonst dieselbe Position (eindeutiger Index, Serverfehler).
            Journey::lockForUpdate()->findOrFail($journeyId);
            $position = (int) JourneySection::where('journey_id', $journeyId)->max('position') + 1;
            $section = JourneySection::create([
                'journey_id' => $journeyId, 'position' => $position, 'kind' => $kind,
                'start_on' => $startOn, 'surcharge_percent' => $surchargePercent,
            ] + ($trip ? [
                'title' => mb_substr($trip->title, 0, 150), 'trip_id' => $trip->id, 'tour_data' => $this->snapshots->tripData($trip), 'plan' => $this->plans->fromTrip($trip),
            ] : ($tour ? [
                // In der Streckenplanung berechnet, ohne Archiv-Eintrag: eigene Kopie, kein trip_id
                'title' => mb_substr(trim((string) ($tour['title'] ?? '')) ?: $kind->label(), 0, 150),
                'tour_data' => $this->snapshots->fromRequest($tour, $kind->label()),
                'plan' => $this->plans->fromRequest($tour),
            ] : [
                'title' => mb_substr(trim((string) ($own['title'] ?? '')), 0, 150) ?: $kind->label(),
                'loaded_km' => $own['loaded_km'] ?? 0, 'drive_s' => $own['drive_s'] ?? 0, 'days' => max(1, (int) ($own['days'] ?? 1)),
            ])));
            $this->saveVehicles($section, $vehicles);
            $this->refreshDates(Journey::findOrFail($journeyId));

            return $section;
        });
    }

    /**
     * Strecke ändern: Titel, Datum, Zuschlag, Fahrzeugbedarf, eine neu berechnete Route und von Hand überschriebene Kennzahlen. Die Art
     * bleibt. Die Positionen im Angebot ändern sich erst mit „Angebot aktualisieren“.
     *
     * @param  list<array{type: ?VehicleType, count: int}>|null  $vehicles  null: unverändert
     * @param  array<string, mixed>|null  $tour  neu berechnete Route (siehe TourPayload); ersetzt Kopie und Plan der Strecke
     * @param  array{loaded_km: float, empty_km: float, drive_s: int, days: int}|null  $figures  gültige Kennzahlen, auch von Hand
     */
    public function updateSection(JourneySection $section, array $data, ?array $vehicles = null, ?array $tour = null, ?array $figures = null): JourneySection
    {
        return DB::transaction(function () use ($section, $data, $vehicles, $tour, $figures) {
            $this->assertActive($section->journey_id);
            $section->fill(array_intersect_key($data, array_flip(['title', 'start_on', 'surcharge_percent'])));
            if ($tour !== null) {
                $section->fill([
                    'tour_data' => $this->snapshots->fromRequest($tour, $section->title),
                    'plan' => $this->plans->fromRequest($tour),
                    // Die Route kommt nicht mehr aus der Tour im Archiv, und eigene Angaben gelten nicht mehr.
                    'trip_id' => null, 'loaded_km' => null, 'drive_s' => null, 'days' => null,
                ]);
            }
            if ($figures !== null) {
                if ($section->tour_data !== null) {
                    $section->tour_data = array_merge($section->tour_data, $figures);
                } else {
                    // Strecke ohne Route: Eigene Angaben kennen keine Leerfahrt.
                    $section->fill(array_intersect_key($figures, array_flip(['loaded_km', 'drive_s', 'days'])));
                }
            }
            if (isset($data['trip_kind']) && $section->tour_data !== null) {
                $section->tour_data = array_merge($section->tour_data, ['trip_kind' => $data['trip_kind']->value, 'trip_kind_label' => $data['trip_kind']->label()]);
            }
            $section->save();
            if ($vehicles !== null) {
                $section->vehicles()->delete();
                $this->saveVehicles($section, $vehicles);
            }
            $this->refreshDates(Journey::findOrFail($section->journey_id));

            return $section->fresh('vehicles');
        });
    }

    /**
     * Eine Strecke zum Bearbeiten in der Streckenplanung: immer der aktuelle Stand. Steht sie in einem festgeschriebenen Beleg,
     * kommt dessen Nummer für den Hinweis mit (der Beleg behält seinen Stand).
     *
     * @return array<string, mixed>
     */
    public function sectionForEdit(Journey $journey, JourneySection $section): array
    {
        $section->load('vehicles');
        $td = $section->tour_data;
        $detail = collect($this->detail($journey)['sections'])->firstWhere('id', $section->id);

        return [
            'journey' => ['id' => $journey->id, 'title' => $journey->title],
            'id' => $section->id, 'kind' => $section->kind->value, 'kind_label' => $section->kind->label(), 'title' => $section->title,
            'start_on' => $section->start_on?->toDateString(), 'surcharge_percent' => (float) $section->surcharge_percent,
            'vehicles' => $section->vehicles->map(fn ($v) => ['type' => $v->vehicle_type?->value, 'count' => $v->vehicle_count])->all(),
            'figures' => $td ? [
                'loaded_km' => (float) $td['loaded_km'], 'empty_km' => (float) $td['empty_km'], 'drive_s' => (int) $td['drive_s'], 'days' => (int) $td['days'],
            ] : [
                'loaded_km' => (float) ($section->loaded_km ?? 0), 'empty_km' => 0.0, 'drive_s' => (int) ($section->drive_s ?? 0), 'days' => max(1, (int) ($section->days ?? 1)),
            ],
            'tour' => $td,
            'plan' => $this->plans->forClient($section->plan),
            'frozen_by' => $detail['trip']['frozen_by'] ?? null,
        ];
    }

    /**
     * Der Zeitraum der Reise aus den Daten ihrer Strecken (erster Tag bis letzter Einsatztag). Strecken ohne Datum zählen nicht;
     * gibt es keines, bleibt der Zeitraum, wie er ist (zum Beispiel aus dem Kurzweg).
     */
    public function refreshDates(Journey $journey): void
    {
        $sections = JourneySection::where('journey_id', $journey->id)->whereNotNull('start_on')->get();
        if ($sections->isEmpty()) {
            return;
        }

        $journey->update([
            'date_from' => $sections->min('start_on'),
            'date_to' => $sections->map(fn (JourneySection $s) => $s->start_on->copy()->addDays(max(1, (int) ($s->tour_data['days'] ?? $s->days ?? 1)) - 1))->max(),
        ]);
    }
    /** @param  list<array{type: ?VehicleType, count: int}>  $vehicles */
    private function saveVehicles(JourneySection $section, array $vehicles): void
    {
        foreach ($vehicles as $v) {
            $section->vehicles()->create(['vehicle_type' => $v['type'], 'vehicle_count' => max(1, (int) $v['count'])]);
        }
    }

    /**
     * Ein Entwurf wurde geändert: Kunde und Fahrtdatum des Belegs gelten auch für die Reise, solange es der erste Beleg ist
     * (ein Folgebeleg ändert die Reise nicht).
     */
    public function syncFromDraft(Document $document): void
    {
        if ($document->journey_id === null || $document->source_id !== null) {
            return;
        }

        $journey = Journey::find($document->journey_id);
        $journey?->update([
            'customer_id' => $document->customer_id,
            // Ein leeres Datum im Beleg löscht nicht den Zeitraum, den die Tour mitgebracht hat.
            'date_from' => $document->service_date_from ?? $journey->date_from,
            'date_to' => $document->service_date_to ?? $journey->date_to,
        ]);
    }

    /**
     * Die Reise eines Belegs für die Anzeige: Titel und alle Belege der Reise in Reihenfolge der Anlage, damit der Beleg die
     * Kette Angebot → Auftrag → Rechnung zeigen kann. Null, wenn der Beleg zu keiner Reise gehört.
     *
     * @return array<string, mixed>|null
     */
    public function summary(Document $document): ?array
    {
        if ($document->journey_id === null) {
            return null;
        }

        $journey = Journey::find($document->journey_id);
        if ($journey === null) {
            return null;
        }

        $documents = Document::where('journey_id', $journey->id)->orderBy('created_at')->orderBy('id')->get();

        return [
            'id' => $journey->id,
            'title' => $journey->title,
            'date_from' => $journey->date_from?->toDateString(),
            'date_to' => $journey->date_to?->toDateString(),
            'sections' => $journey->sections()->with('vehicles')->get()->map(fn (JourneySection $s) => [
                'id' => $s->id, 'kind' => $s->kind->value, 'kind_label' => $s->kind->label(), 'title' => $s->title, 'trip_id' => $s->trip_id,
                'own' => $s->ownFigures(),
                'vehicles' => $s->vehicles->map(fn ($v) => ['type' => $v->vehicle_type?->value, 'label' => $v->vehicle_type?->label(), 'count' => $v->vehicle_count])->all(),
            ])->all(),
            'documents' => $documents->map(fn (Document $d) => [
                'id' => $d->id, 'type' => $d->type->value, 'number' => $d->number, 'status' => $d->status->value,
                'cancelled' => $d->cancelled_by_id !== null,
            ])->all(),
            'trip_orders' => $this->tripOrderSummary($journey, $documents),
        ] + $this->stateFields($journey);
    }

    /**
     * Der Stand der Reise, aus ihren Belegen abgeleitet (nichts davon ist gespeichert): der weitestgehende gültige Beleg
     * (Rechnung vor Auftragsbestätigung vor Angebot, nicht storniert, bei gleicher Art der neueste) mit seinem Betrag.
     *
     * @param  iterable<Document>  $documents  die Belege EINER Reise
     * @return array{stage: ?string, stage_document_id: ?string, amount: ?string, document_count: int}
     */
    public function overview(iterable $documents): array
    {
        $rank = ['angebot' => 1, 'auftragsbestaetigung' => 2, 'rechnung' => 3];
        $best = null;
        $count = 0;
        foreach ($documents as $d) {
            $count++;
            $r = $rank[$d->type->value] ?? 0;
            if ($r === 0 || $d->cancelled_by_id !== null) {
                continue;
            }
            if ($best === null || $r > $rank[$best->type->value] || ($r === $rank[$best->type->value] && $d->created_at >= $best->created_at)) {
                $best = $d;
            }
        }

        return [
            'stage' => $best?->type->value,
            'stage_document_id' => $best?->id,
            'amount' => $best?->gross_total,
            'document_count' => $count,
        ];
    }

    /**
     * Alles zu einer Reise für die Detailseite: Strecken mit Fahrzeugbedarf und Tour, Belege mit Betrag und Stand.
     *
     * @return array<string, mixed>
     */
    public function detail(Journey $journey): array
    {
        $journey->load(['customer', 'sections.vehicles']);
        $documents = Document::where('journey_id', $journey->id)->orderBy('created_at')->orderBy('id')->get();

        // Festgehaltener Stand je Strecke: aus dem neuesten gültigen festgeschriebenen Beleg, der sie beschreibt. So zeigt die Reise
        // dasselbe wie Beleg und PDF, auch wenn die Tour im Archiv später geändert wurde.
        $frozen = [];
        foreach ($documents->sortByDesc('finalized_at') as $d) {
            if ($d->tour_snapshot === null || $d->cancelled_by_id !== null) {
                continue;
            }
            foreach ($d->tour_snapshot['sections'] ?? [] as $snap) {
                if (! isset($frozen[$snap['section_id']]) && ! empty($snap['trip'])) {
                    $frozen[$snap['section_id']] = ['trip' => $snap['trip'], 'number' => $d->number];
                }
            }
        }

        return [
            'id' => $journey->id,
            'title' => $journey->title,
            'date_from' => $journey->date_from?->toDateString(),
            'date_to' => $journey->date_to?->toDateString(),
            'customer' => $journey->customer ? [
                'id' => $journey->customer->id, 'customer_number' => $journey->customer->customer_number, 'display_name' => $journey->customer->display_name,
            ] : null,
            'sections' => $journey->sections->map(fn (JourneySection $sec) => [
                'id' => $sec->id, 'kind' => $sec->kind->value, 'kind_label' => $sec->kind->label(), 'title' => $sec->title,
                'start_on' => $sec->start_on?->toDateString(), 'surcharge_percent' => (float) $sec->surcharge_percent,
                'own' => $sec->ownFigures(),
                'vehicles' => $sec->vehicles->map(fn ($v) => ['type' => $v->vehicle_type?->value, 'label' => $v->vehicle_type?->label(), 'count' => $v->vehicle_count])->all(),
                'trip' => isset($frozen[$sec->id]) ? [
                    // Stand beim Festschreiben (Beleg-Nummer in frozen_by); der Verweis auf die Tour bleibt zum Öffnen im Archiv
                    'id' => $sec->trip_id, 'title' => $frozen[$sec->id]['trip']['title'],
                    'loaded_km' => $frozen[$sec->id]['trip']['loaded_km'], 'empty_km' => $frozen[$sec->id]['trip']['empty_km'],
                    'drive_s' => $frozen[$sec->id]['trip']['drive_s'], 'days' => $frozen[$sec->id]['trip']['days'],
                    'from' => $frozen[$sec->id]['trip']['stops'][0]['label'] ?? null,
                    'to' => collect($frozen[$sec->id]['trip']['stops'])->last()['label'] ?? null,
                    'frozen_by' => $frozen[$sec->id]['number'],
                ] : ($sec->tour_data ? [
                    // Noch kein festgeschriebener Beleg: die Kopie der Strecke aus dem Angebot, nie die Tour im Archiv
                    'id' => $sec->trip_id, 'title' => $sec->tour_data['title'], 'loaded_km' => $sec->tour_data['loaded_km'], 'empty_km' => $sec->tour_data['empty_km'],
                    'drive_s' => $sec->tour_data['drive_s'], 'days' => $sec->tour_data['days'],
                    'from' => $sec->tour_data['stops'][0]['label'] ?? null, 'to' => collect($sec->tour_data['stops'])->last()['label'] ?? null,
                    'frozen_by' => null,
                ] : null),
            ])->all(),
            'documents' => $documents->map(fn (Document $d) => [
                'id' => $d->id, 'type' => $d->type->value, 'number' => $d->number, 'status' => $d->status->value, 'cancelled' => $d->cancelled_by_id !== null,
                'document_date' => $d->document_date?->toDateString(), 'gross_total' => $d->gross_total, 'subject' => $d->subject,
            ])->all(),
            'trip_orders' => $this->tripOrderSummary($journey, $documents),
            'trip_order_list' => $this->tripOrderRows($journey),
        ] + $this->overview($documents) + $this->stateFields($journey);
    }

    /**
     * Die Fahrtaufträge für die Reise-Seite, in der Reihenfolge der Strecken und Busse.
     *
     * @return list<array<string, mixed>>
     */
    private function tripOrderRows(Journey $journey): array
    {
        $positions = $journey->sections->pluck('position', 'id');

        return TripOrder::whereIn('journey_section_id', $positions->keys())->with(['vehicle', 'driver', 'driver2'])->get()
            ->sortBy([fn (TripOrder $a, TripOrder $b) => [$positions[$a->journey_section_id], $a->vehicle_type?->value, $a->bus_no]
                <=> [$positions[$b->journey_section_id], $b->vehicle_type?->value, $b->bus_no]])
            ->values()
            ->map(fn (TripOrder $o) => [
                'id' => $o->id, 'number' => $o->number, 'title' => $o->title, 'bus_no' => $o->bus_no,
                'vehicle_type_label' => $o->vehicle_type?->label(),
                'status' => $o->status->value, 'status_label' => $o->isCancelled() ? 'Abgesagt' : $o->status->label(),
                'cancelled_at' => $o->cancelled_at?->toIso8601String(),
                'date_from' => $o->date_from?->toDateString(), 'date_to' => $o->date_to?->toDateString(),
                'vehicle' => $o->vehicle?->display_name, 'external_company' => $o->external_company,
                'drivers' => array_values(array_filter([$o->driver?->display_name, $o->driver2?->display_name])),
            ])->all();
    }

    /**
     * Die Fahrtaufträge einer Reise im Überblick (abgeleitet, nichts gespeichert). „expected“ ist die Zahl der Busse in der neuesten
     * gültigen festgeschriebenen Auftragsbestätigung; weicht sie von den vorhandenen Fahrtaufträgen ab (Bus dazu oder weniger), steht
     * „check“ auf true und die Oberfläche sagt „Fahrtaufträge prüfen“. Ohne Auftragsbestätigung ist nichts zu erwarten.
     *
     * @param  iterable<Document>  $documents  die Belege EINER Reise
     * @return array{count: int, to_plan: int, planned: int, issued: int, cancelled: int, expected: ?int, check: bool}
     */
    public function tripOrderSummary(Journey $journey, iterable $documents): array
    {
        $orders = fn () => TripOrder::whereIn('journey_section_id', $journey->sections()->reorder()->select('id'));
        $byStatus = $orders()->whereNull('cancelled_at')->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $cancelled = $orders()->whereNotNull('cancelled_at')->count();
        $count = (int) $byStatus->sum() + $cancelled;

        $confirmation = collect($documents)
            ->filter(fn (Document $d) => $d->type === DocumentType::Auftragsbestaetigung && $d->status === DocumentStatus::Festgeschrieben && $d->cancelled_by_id === null)
            ->sortByDesc('finalized_at')->first();
        $expected = $confirmation === null ? null : (int) collect($confirmation->tour_snapshot['sections'] ?? [])
            ->sum(fn ($s) => collect($s['vehicles'] ?? [])->sum(fn ($v) => max(1, (int) ($v['count'] ?? 1))));

        return [
            'count' => $count,
            'to_plan' => (int) ($byStatus['entwurf'] ?? 0),
            'planned' => (int) ($byStatus['disponiert'] ?? 0),
            'issued' => (int) (($byStatus['ausgegeben'] ?? 0) + ($byStatus['erledigt'] ?? 0)),
            'cancelled' => $cancelled,
            'expected' => $expected,
            // Bei einer abgesagten Reise gibt es nichts zu prüfen.
            'check' => ! $journey->isCancelled() && $expected !== null && $expected !== $count,
        ];
    }

    /** Ein Entwurf wurde gelöscht: Eine Reise des Kurzwegs ohne Belege bleibt nicht übrig, eine von Hand geplante schon. */
    public function removeIfEmpty(?string $journeyId): void
    {
        if ($journeyId === null || Document::where('journey_id', $journeyId)->exists()) {
            return;
        }

        // Eine von Hand geplante Reise (Neue Reise planen) bleibt auch ohne Entwurf bestehen; nur die des Kurzwegs verschwindet.
        $journey = Journey::whereKey($journeyId)->first();
        if ($journey && ! $journey->manual) {
            $journey->delete();
        }
    }
}
