<?php

namespace App\Services;

use App\Enums\SectionKind;
use App\Enums\VehicleType;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Journey;
use App\Models\JourneySection;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;

/**
 * Reisevorhaben: entstehen automatisch mit dem ersten Angebot aus der Streckenplanung, niemand legt sie von Hand an.
 * Der Dienst hält nur die Klammer (Kunde, Titel, Zeitraum, Abschnitte); Summen und Stand werden aus den Belegen abgeleitet.
 */
class JourneyService
{
    /**
     * Legt ein Reisevorhaben mit seinem ersten Abschnitt (Hinfahrt) an.
     *
     * @param  list<array{type: ?VehicleType, count: int}>  $vehicles  Fahrzeugbedarf der Hinfahrt
     */
    public function start(string $title, ?string $customerId, ?string $dateFrom, ?string $dateTo, ?string $tripId, array $vehicles = []): Journey
    {
        return DB::transaction(function () use ($title, $customerId, $dateFrom, $dateTo, $tripId, $vehicles) {
            $title = mb_substr(trim($title) !== '' ? trim($title) : 'Reise', 0, 150);
            $journey = Journey::create(['title' => $title, 'customer_id' => $customerId, 'date_from' => $dateFrom, 'date_to' => $dateTo]);
            $section = $journey->sections()->create(['position' => 1, 'kind' => SectionKind::Hinfahrt, 'title' => $title, 'trip_id' => $tripId]);
            $this->saveVehicles($section, $vehicles);

            return $journey;
        });
    }

    /**
     * Ein weiterer Abschnitt der Reise mit der Strecke einer gespeicherten Tour und seinem Fahrzeugbedarf.
     *
     * @param  list<array{type: ?VehicleType, count: int}>  $vehicles
     */
    public function addSection(string $journeyId, SectionKind $kind, Trip $trip, array $vehicles): JourneySection
    {
        return DB::transaction(function () use ($journeyId, $kind, $trip, $vehicles) {
            $position = (int) JourneySection::where('journey_id', $journeyId)->max('position') + 1;
            $section = JourneySection::create([
                'journey_id' => $journeyId, 'position' => $position, 'kind' => $kind, 'title' => mb_substr($trip->title, 0, 150), 'trip_id' => $trip->id,
            ]);
            $this->saveVehicles($section, $vehicles);

            return $section;
        });
    }

    /**
     * Entfernt einen Abschnitt, wenn keine Position eines Belegs mehr auf ihn zeigt. Der erste und einzige Abschnitt bleibt.
     * Die Positionen des Entwurfs muss der Aufrufer vorher entfernt haben.
     *
     * @return bool ob der Abschnitt entfernt wurde
     */
    public function removeSectionIfUnused(JourneySection $section): bool
    {
        if (DocumentLine::where('journey_section_id', $section->id)->exists()
            || JourneySection::where('journey_id', $section->journey_id)->count() <= 1) {
            return false;
        }
        $section->delete();

        return true;
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

        return [
            'id' => $journey->id,
            'title' => $journey->title,
            'date_from' => $journey->date_from?->toDateString(),
            'date_to' => $journey->date_to?->toDateString(),
            'sections' => $journey->sections()->with('vehicles')->get()->map(fn (JourneySection $s) => [
                'id' => $s->id, 'kind' => $s->kind->value, 'kind_label' => $s->kind->label(), 'title' => $s->title, 'trip_id' => $s->trip_id,
                'vehicles' => $s->vehicles->map(fn ($v) => ['type' => $v->vehicle_type?->value, 'label' => $v->vehicle_type?->label(), 'count' => $v->vehicle_count])->all(),
            ])->all(),
            'documents' => Document::where('journey_id', $journey->id)->orderBy('created_at')->orderBy('id')
                ->get(['id', 'type', 'number', 'status', 'cancelled_by_id', 'cancels_id'])
                ->map(fn (Document $d) => [
                    'id' => $d->id, 'type' => $d->type->value, 'number' => $d->number, 'status' => $d->status->value,
                    'cancelled' => $d->cancelled_by_id !== null,
                ])->all(),
        ];
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
        $journey->load(['customer', 'sections.vehicles', 'sections.trip.stops']);
        $documents = Document::where('journey_id', $journey->id)->orderBy('created_at')->orderBy('id')->get();

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
                'vehicles' => $sec->vehicles->map(fn ($v) => ['type' => $v->vehicle_type?->value, 'label' => $v->vehicle_type?->label(), 'count' => $v->vehicle_count])->all(),
                'trip' => $sec->trip ? [
                    'id' => $sec->trip->id, 'title' => $sec->trip->title, 'loaded_km' => $sec->trip->loaded_km, 'empty_km' => $sec->trip->empty_km,
                    'drive_s' => $sec->trip->drive_s, 'days' => $sec->trip->days,
                    'from' => $sec->trip->stops->first()?->label, 'to' => $sec->trip->stops->last()?->label,
                ] : null,
            ])->all(),
            'documents' => $documents->map(fn (Document $d) => [
                'id' => $d->id, 'type' => $d->type->value, 'number' => $d->number, 'status' => $d->status->value, 'cancelled' => $d->cancelled_by_id !== null,
                'document_date' => $d->document_date?->toDateString(), 'gross_total' => $d->gross_total, 'subject' => $d->subject,
            ])->all(),
        ] + $this->overview($documents);
    }

    /** Ein Entwurf wurde gelöscht: Eine Reise ohne Belege bleibt nicht übrig. */
    public function removeIfEmpty(?string $journeyId): void
    {
        if ($journeyId === null || Document::where('journey_id', $journeyId)->exists()) {
            return;
        }

        Journey::whereKey($journeyId)->first()?->delete();
    }
}
