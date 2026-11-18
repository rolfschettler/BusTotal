<?php

namespace App\Routing;

/** Findet zu einem Suchtext (Ort, Adresse, Sehenswürdigkeit) Koordinaten. Austauschbar wie der RoutingProvider. */
interface GeocodingProvider
{
    /**
     * @return list<GeocodeHit>
     *
     * @throws RoutingException
     */
    public function search(string $query, int $limit = 5): array;
}
