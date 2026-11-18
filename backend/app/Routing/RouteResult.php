<?php

namespace App\Routing;

/** Ergebnis einer Routenberechnung über mehrere Punkte (Linienzug als [lat, lon]-Paare für die Karte). */
final readonly class RouteResult
{
    /**
     * @param  list<array{distance_m: int, duration_s: int}>  $legs
     * @param  list<array{float, float}>  $geometry
     */
    public function __construct(
        public int $distanceM,
        public int $durationS,
        public array $legs,
        public array $geometry = [],
    ) {}
}
