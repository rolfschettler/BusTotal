<?php

namespace App\Services;

use App\Models\Trip;
use Illuminate\Support\Facades\DB;

/**
 * Tour-Archiv: speichert eine geplante Tour mit ihren Halten. Die Orte der Halte kommen aus der Planung (schon gesucht) und
 * werden über PlaceResolver::remember() in der zentralen Ortstabelle abgelegt, ohne den Adressdienst anzurufen.
 */
class TripService
{
    public function __construct(private readonly PlaceResolver $places) {}

    /**
     * @param  array<string, mixed>  $data  geprüfte Eingabe (siehe TripController::rules)
     */
    public function save(Trip $trip, array $data): Trip
    {
        return DB::transaction(function () use ($trip, $data) {
            $stops = $data['stops'];
            unset($data['stops']);

            $data['geometry'] = isset($data['geometry']) ? $this->thin($data['geometry']) : null;
            $trip->fill($data);
            if (! $trip->exists) {
                $trip->created_by = auth()->id();
            }
            $trip->save();

            $trip->stops()->delete();
            foreach (array_values($stops) as $i => $stop) {
                $place = $this->places->remember($stop['label'], (float) $stop['lat'], (float) $stop['lon']);
                $trip->stops()->create([
                    'position' => $i + 1,
                    'label' => mb_substr(trim($stop['label']), 0, 255),
                    'place_id' => $place->id,
                    'dwell_minutes' => (int) ($stop['dwell_minutes'] ?? 0),
                    'driver_free' => (bool) ($stop['driver_free'] ?? false),
                ]);
            }

            return $trip->load('stops');
        });
    }

    /**
     * Linienzug als Liste von [lat, lon] auf fünf Nachkommastellen (rund 1 m) gekürzt: spart Platz und verwirft Unbrauchbares.
     *
     * @param  list<mixed>  $points
     * @return list<array{float, float}>
     */
    private function thin(array $points): array
    {
        $out = [];
        foreach ($points as $p) {
            if (is_array($p) && isset($p[0], $p[1]) && is_numeric($p[0]) && is_numeric($p[1])) {
                $out[] = [round((float) $p[0], 5), round((float) $p[1], 5)];
            }
        }

        return $out;
    }
}
