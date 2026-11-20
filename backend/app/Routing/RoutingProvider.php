<?php

namespace App\Routing;

/**
 * Berechnet Straßenrouten. Der Rest der Anwendung kennt nur diese Schnittstelle, nie einen bestimmten Dienst:
 * Wer später einen anderen Dienst (GraphHopper, eigener Server) will, schreibt eine neue Klasse und tauscht sie
 * im AppServiceProvider aus.
 */
interface RoutingProvider
{
    /**
     * Route über alle Punkte in der gegebenen Reihenfolge mit dem Bus-/Lkw-Profil.
     *
     * @param  list<Coordinate>  $points  mindestens zwei
     *
     * @throws RoutingException
     */
    public function route(array $points, ?VehicleLimits $limits = null): RouteResult;

    /**
     * Fahrzeitmatrix zwischen allen Punkten (Sekunden; null: nicht erreichbar), für die Reihenfolge der Halte.
     *
     * @param  list<Coordinate>  $points
     * @return list<list<?float>>
     *
     * @throws RoutingException
     */
    public function durations(array $points): array;
}
