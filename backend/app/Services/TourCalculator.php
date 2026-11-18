<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Routing\Coordinate;
use App\Routing\DrivingTimePlanner;
use App\Routing\RoutingException;
use App\Routing\RoutingProvider;
use App\Routing\VehicleLimits;
use Carbon\CarbonImmutable;

/**
 * Berechnet eine Tour: Strecke und Fahrzeit über den Routing-Dienst, danach den zeitlichen Ablauf mit Pausen und
 * Ruhezeiten (DrivingTimePlanner). Das Ergebnis ist die Grundlage der Kalkulation (Kilometer, Fahrzeit, Dauer,
 * Leerkilometer) und hat keine Seiteneffekte: Es wird nichts gespeichert.
 *
 * Leerfahrten: Gibt es ein Depot, sind die Fahrt vom Depot zum ersten Halt und vom letzten Halt zurück Leerfahrten
 * (ohne Fahrgäste). Sie werden getrennt ausgewiesen, weil sie anders berechnet werden als die besetzte Strecke.
 */
class TourCalculator
{
    public function __construct(private readonly RoutingProvider $routing, private readonly DrivingTimePlanner $planner) {}

    /**
     * @param  list<array{lat: float|int|string, lon: float|int|string, label?: ?string, dwell_minutes?: ?int, driver_free?: ?bool}>  $stops  Halte in Reihenfolge; Start und Ziel eingeschlossen
     * @param  array{lat: float|int|string, lon: float|int|string, label?: ?string}|null  $depot  Betriebshof; Leerfahrten dorthin und zurück
     * @return array<string, mixed>
     *
     * @throws RoutingException
     */
    public function calculate(array $stops, ?array $depot = null, bool $returnToDepot = true, ?Vehicle $vehicle = null, ?CarbonImmutable $startAt = null, int $extendedDays = 0): array
    {
        // Alle Punkte in Fahrtreihenfolge. Das Depot hat keinen Halt (Aufenthalt 0).
        $points = [];
        if ($depot) {
            $points[] = $this->point($depot, 'Betriebshof', true);
        }
        foreach ($stops as $i => $stop) {
            $points[] = $this->point($stop, 'Halt '.($i + 1), false);
        }
        if ($depot && $returnToDepot) {
            $points[] = $this->point($depot, 'Betriebshof', true);
        }

        if (count($points) < 2) {
            throw new RoutingException('Für eine Route braucht es mindestens zwei Orte.', RoutingException::REJECTED);
        }

        $result = $this->routing->route(array_map(fn ($p) => $p['coordinate'], $points), $vehicle ? VehicleLimits::fromVehicle($vehicle) : null);

        if (count($result->legs) !== count($points) - 1) {
            throw new RoutingException('Der Routing-Dienst hat eine unerwartete Antwort geliefert.', RoutingException::UNAVAILABLE);
        }

        $legs = [];
        $plannerLegs = [];
        $emptyM = 0;
        foreach ($result->legs as $i => $leg) {
            $from = $points[$i];
            $to = $points[$i + 1];
            // Leerfahrt: der Abschnitt beginnt oder endet im Betriebshof.
            $empty = $from['depot'] || $to['depot'];
            $emptyM += $empty ? $leg['distance_m'] : 0;

            $legs[] = [
                'from' => $this->describe($from),
                'to' => $this->describe($to),
                'distance_m' => $leg['distance_m'],
                'duration_s' => $leg['duration_s'],
                'empty' => $empty,
            ];
            $plannerLegs[] = ['drive_s' => $leg['duration_s'], 'dwell_s' => $to['dwell_s'], 'driver_free' => $to['driver_free']];
        }

        $plan = $this->planner->plan($plannerLegs, $points[0]['dwell_s'], $extendedDays);

        $km = $result->distanceM / 1000;

        return [
            'distance_m' => $result->distanceM,
            'empty_distance_m' => $emptyM,
            'loaded_distance_m' => $result->distanceM - $emptyM,
            'drive_s' => $plan['summary']['drive_s'],
            'elapsed_s' => $plan['summary']['elapsed_s'],
            'days' => $plan['summary']['days'],
            'legs' => $legs,
            'schedule' => $this->schedule($plan['events'], $startAt),
            'summary' => $plan['summary'],
            'warnings' => $plan['warnings'],
            'geometry' => $result->geometry,
            'start_at' => $startAt?->toIso8601String(),
            'end_at' => $startAt?->addSeconds($plan['summary']['elapsed_s'])->toIso8601String(),
            'vehicle' => $vehicle ? ['id' => $vehicle->id, 'display_name' => $vehicle->display_name, 'cost_per_km' => $vehicle->cost_per_km] : null,
            // Kilometer mal Kostensatz des Fahrzeugs: nur eine Rechengrundlage, keine fertige Kalkulation (die kommt mit den Belegen).
            'vehicle_cost' => $vehicle?->cost_per_km !== null ? round($km * $vehicle->cost_per_km, 2) : null,
        ];
    }

    /** @return array{coordinate: Coordinate, label: string, depot: bool, dwell_s: int, driver_free: bool} */
    private function point(array $p, string $defaultLabel, bool $depot): array
    {
        $label = isset($p['label']) && trim((string) $p['label']) !== '' ? trim((string) $p['label']) : $defaultLabel;

        return [
            'coordinate' => new Coordinate((float) $p['lat'], (float) $p['lon']),
            'label' => $label,
            'depot' => $depot,
            'dwell_s' => $depot ? 0 : max(0, (int) ($p['dwell_minutes'] ?? 0)) * 60,
            'driver_free' => $depot ? false : (bool) ($p['driver_free'] ?? false),
        ];
    }

    /** @return array{label: string, lat: float, lon: float} */
    private function describe(array $point): array
    {
        return ['label' => $point['label']] + $point['coordinate']->toArray();
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function schedule(array $events, ?CarbonImmutable $startAt): array
    {
        return array_map(function (array $e) use ($startAt) {
            if ($startAt) {
                $e['start_at'] = $startAt->addSeconds($e['start_s'])->toIso8601String();
                $e['end_at'] = $startAt->addSeconds($e['start_s'] + $e['duration_s'])->toIso8601String();
            }

            return $e;
        }, $events);
    }
}
