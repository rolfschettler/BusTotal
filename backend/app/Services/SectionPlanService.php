<?php

namespace App\Services;

use App\Models\Place;
use App\Models\Trip;

/**
 * Der Plan einer Strecke (journey_sections.plan): damit die Streckenplanung sie später so öffnet, wie sie geplant wurde, ohne neue
 * Anfrage beim Orts- oder Routing-Dienst. Die Lage der Halte steht in der Ortstabelle (places), der Plan verweist mit place_id darauf.
 */
class SectionPlanService
{
    public function __construct(private readonly PlaceResolver $places) {}

    /**
     * Aus den geprüften Angaben der Anfrage (TourPayload, Schlüssel `plan`). Ohne diese Angabe: null.
     *
     * @param  array<string, mixed>|null  $tour
     * @return array<string, mixed>|null
     */
    public function fromRequest(?array $tour): ?array
    {
        $plan = $tour['plan'] ?? null;
        if (! $plan) {
            return null;
        }

        $place = fn (array $p): array => [
            'label' => (string) $p['label'],
            'place_id' => $this->places->remember($p['label'], (float) $p['lat'], (float) $p['lon'])->id,
        ];

        return [
            'depot_id' => $plan['depot_id'] ?? null,
            'depot_other' => ! empty($plan['depot_other']) ? $place($plan['depot_other']) : null,
            'from_depot' => (bool) ($plan['from_depot'] ?? true),
            'return_to_depot' => (bool) ($plan['return_to_depot'] ?? false),
            'start_at' => $plan['start_at'] ?? null,
            'stops' => array_map(fn (array $s) => $place($s) + [
                'dwell_minutes' => (int) ($s['dwell_minutes'] ?? 0), 'driver_free' => (bool) ($s['driver_free'] ?? false),
            ], array_values($plan['stops'])),
            'route' => $plan['route'] ?? null,
            'geometry' => $plan['geometry'] ?? null,
            'calculated' => $this->figures($tour),
        ];
    }

    /** Der Plan einer Tour aus dem Archiv, die als Vorlage übernommen wird. @return array<string, mixed> */
    public function fromTrip(Trip $trip): array
    {
        $trip->loadMissing('stops');

        return [
            'depot_id' => $trip->depot_id,
            'depot_other' => null,
            'from_depot' => (bool) ($trip->from_depot ?? true),
            'return_to_depot' => (bool) $trip->return_to_depot,
            'start_at' => null,
            'stops' => $trip->stops->map(fn ($s) => [
                'label' => $s->label, 'place_id' => $s->place_id, 'dwell_minutes' => (int) $s->dwell_minutes, 'driver_free' => (bool) $s->driver_free,
            ])->all(),
            'route' => $trip->route,
            'geometry' => $trip->geometry,
            'calculated' => $this->figures($trip->only(['loaded_km', 'empty_km', 'drive_s', 'days'])),
        ];
    }

    /**
     * Der Plan für die Oberfläche: mit den Koordinaten der Orte.
     *
     * @param  array<string, mixed>|null  $plan
     * @return array<string, mixed>|null
     */
    public function forClient(?array $plan): ?array
    {
        if ($plan === null) {
            return null;
        }

        $other = $plan['depot_other'] ?? null;
        $ids = collect($plan['stops'])->pluck('place_id')->push($other['place_id'] ?? null)->filter()->all();
        $known = Place::whereIn('id', $ids)->get()->keyBy('id');
        $withPlace = function (array $p) use ($known): array {
            $place = $known->get($p['place_id']);

            return $p + ['lat' => $place?->lat, 'lon' => $place?->lon];
        };

        $plan['stops'] = array_map($withPlace, $plan['stops']);
        $plan['depot_other'] = $other ? $withPlace($other) : null;

        return $plan;
    }

    /** @return array{loaded_km: float, empty_km: float, drive_s: int, days: int} */
    private function figures(array $t): array
    {
        return ['loaded_km' => (float) $t['loaded_km'], 'empty_km' => (float) $t['empty_km'], 'drive_s' => (int) $t['drive_s'], 'days' => max(1, (int) $t['days'])];
    }
}
