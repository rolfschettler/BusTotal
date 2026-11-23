<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\JourneySection;
use App\Models\Trip;

/**
 * Hält beim Festschreiben die Fahrtbeschreibung eines Belegs fest: je Strecke Halte, Kilometer, Zeiten und Fahrzeuge, so wie sie in
 * diesem Moment im Tour-Archiv stehen. Der Beleg zeigt später genau das, auch wenn die Tour im Archiv geändert oder gelöscht wird.
 *
 * Folgebelege übernehmen den Stand ihres Vorgängers (die Auftragsbestätigung beschreibt, was angeboten wurde); nur Strecken, die
 * der Vorgänger nicht kannte, werden neu aus dem Archiv gelesen. Der Linienzug gehört nicht dazu (zu groß, für den Beleg ohne Wert).
 */
class TourSnapshotService
{
    /** Belegarten mit Fahrtbeschreibung: Anzahlung und Storno haben keine. */
    private const TYPES = [DocumentType::Angebot, DocumentType::Auftragsbestaetigung, DocumentType::Rechnung];

    /** @return array{created_at: string, sections: list<array<string, mixed>>}|null */
    public function build(Document $document): ?array
    {
        if (! in_array($document->type, self::TYPES, true)) {
            return null;
        }

        $document->loadMissing('lines');
        $ids = $document->lines->pluck('journey_section_id')->filter()->unique()->values()->all();
        if ($ids === []) {
            return null;
        }

        $known = collect($document->source?->tour_snapshot['sections'] ?? [])->keyBy('section_id');
        $sections = JourneySection::with(['vehicles', 'trip.stops'])->whereIn('id', $ids)->orderBy('position')->get();

        return [
            'created_at' => now()->toIso8601String(),
            'sections' => $sections->map(function (JourneySection $section) use ($known, $document) {
                // Welche Fahrzeugtypen dieser Strecke stehen wirklich in den Positionen dieses Belegs?
                $types = $document->lines->where('journey_section_id', $section->id)->pluck('vehicle_type')->unique()->all();

                $entry = $known->get($section->id) ?? $this->fromArchive($section);
                $entry['vehicles'] = $section->vehicles
                    ->filter(fn ($v) => in_array($v->vehicle_type?->value, $types, true))
                    ->map(fn ($v) => ['type' => $v->vehicle_type?->value, 'label' => $v->vehicle_type?->label(), 'count' => $v->vehicle_count])
                    ->values()->all();

                return $entry;
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function fromArchive(JourneySection $section): array
    {
        return [
            'section_id' => $section->id,
            'kind' => $section->kind->value,
            'kind_label' => $section->kind->label(),
            'title' => $section->title,
            'trip' => $section->trip ? $this->tripData($section->trip) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function tripData(Trip $trip): array
    {
        return [
            'title' => $trip->title,
            'trip_kind' => $trip->trip_kind->value,
            'trip_kind_label' => $trip->trip_kind->label(),
            'loaded_km' => $trip->loaded_km,
            'empty_km' => $trip->empty_km,
            'drive_s' => $trip->drive_s,
            'elapsed_s' => $trip->elapsed_s,
            'days' => $trip->days,
            'round_trip' => $trip->round_trip,
            'stops' => $trip->stops->map(fn ($s) => ['label' => $s->label, 'dwell_minutes' => $s->dwell_minutes, 'driver_free' => $s->driver_free])->all(),
            // Teilstrecken der berechneten Route (Von, Nach, Länge, Dauer, Leerfahrt); ohne gespeicherte Route leer
            'legs' => collect($trip->route['legs'] ?? [])->map(fn ($l) => [
                'from' => $l['from']['label'] ?? '', 'to' => $l['to']['label'] ?? '',
                'distance_m' => (int) ($l['distance_m'] ?? 0), 'duration_s' => (int) ($l['duration_s'] ?? 0), 'empty' => (bool) ($l['empty'] ?? false),
            ])->all(),
        ];
    }
}
